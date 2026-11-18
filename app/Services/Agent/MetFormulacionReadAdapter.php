<?php

namespace App\Services\Agent;

use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\EstadoConvenioEstablecimiento;
use App\Models\CenCenter;
use App\Models\IndicatorType;
use App\Models\MetDataField;
use App\Models\MetEstablishmentAgreement;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetIndicatorPeriodicityResolver;
use App\Support\Agent\AgentPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MetFormulacionReadAdapter
{
    public function __construct(
        private readonly MetasReadAdapter $metas,
        private readonly MetIndicatorPeriodicityResolver $periodicityResolver,
    ) {}

    /**
     * Centro del SS del token, o null si no existe / pertenece a otro SS.
     */
    public function findCenterInSs(int $centerId, int $ssId): ?CenCenter
    {
        if ($centerId < 1) {
            return null;
        }

        $center = CenCenter::query()->find($centerId);
        if (! $center instanceof CenCenter) {
            return null;
        }

        if ((int) $center->cen_hie_dependency_id !== $ssId) {
            return null;
        }

        return $center;
    }

    public function supportsLey(?string $ley): bool
    {
        return $this->metas->supportsLey($ley) && $ley !== null && $ley !== '';
    }

    /**
     * @return LengthAwarePaginator<int, MetIndicatorConfiguration>
     */
    public function paginate(
        Year $year,
        int $ssId,
        string $ley,
        ?int $establecimientoId,
        ?string $estado,
        ?bool $aplica,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        $typeIds = $this->typeIdsForLey($ley);
        if ($typeIds === []) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $query = MetIndicatorConfiguration::query()
            ->where('year_id', $year->id)
            ->where('cen_hie_dependency_id', $ssId)
            ->whereHas('metIndicator', function (Builder $b) use ($year, $typeIds): void {
                $b->where('year_id', $year->id)
                    ->whereIn('indicator_type_id', $typeIds);
            });

        if ($establecimientoId !== null) {
            $query->where('cen_center_id', $establecimientoId);
        }

        if ($aplica !== null) {
            if ($aplica === true) {
                $query->where(function (Builder $b): void {
                    $b->where('aplica', true)->orWhereNull('aplica');
                });
            } else {
                $query->where('aplica', false);
            }
        }

        if ($estado !== null && $estado !== '') {
            $this->applyEstadoFilter($query, $year, $typeIds, $estado);
        }

        $query->with([
            'center:id,codigo,nombre_oficial,nombre_corto',
            'measurementUnit:id,codigo,nombre,cen_center_id',
            'metIndicator.indicatorType:id,route',
            'metIndicator.formula:id,met_indicator_id,description',
            'metIndicator.dataFields',
        ])
            ->orderBy('cen_center_id')
            ->orderBy('met_indicator_id')
            ->orderBy('met_measurement_unit_id');

        /** @var LengthAwarePaginator<int, MetIndicatorConfiguration> $pageResult */
        $pageResult = $query->paginate($perPage, ['*'], 'page', $page);

        $this->eagerLoadAgreements($pageResult->getCollection(), $year, $ssId);

        return $pageResult;
    }

    /**
     * @param  Collection<int, MetIndicatorConfiguration>  $configs
     */
    private function eagerLoadAgreements(Collection $configs, Year $year, int $ssId): void
    {
        if ($configs->isEmpty()) {
            return;
        }

        $centerIds = $configs->pluck('cen_center_id')->unique()->filter()->map(fn($id) => (int) $id)->values()->all();
        $typeIds = $configs
            ->map(fn(MetIndicatorConfiguration $c) => (int) ($c->metIndicator?->indicator_type_id ?? 0))
            ->unique()
            ->filter(fn(int $id) => $id > 0)
            ->values()
            ->all();

        if ($centerIds === [] || $typeIds === []) {
            $configs->each(fn(MetIndicatorConfiguration $c) => $c->setRelation('agentAgreement', null));

            return;
        }

        $agreements = MetEstablishmentAgreement::query()
            ->where('year_id', $year->id)
            ->where('cen_hie_dependency_id', $ssId)
            ->whereIn('cen_center_id', $centerIds)
            ->whereIn('indicator_type_id', $typeIds)
            ->with(['submittedBy:id,name'])
            ->get();

        $byKey = $agreements->keyBy(
            fn(MetEstablishmentAgreement $a) => $this->agreementKey(
                (int) $a->cen_center_id,
                (int) $a->indicator_type_id
            )
        );

        foreach ($configs as $config) {
            $typeId = (int) ($config->metIndicator?->indicator_type_id ?? 0);
            $key = $this->agreementKey((int) $config->cen_center_id, $typeId);
            $config->setRelation('agentAgreement', $byKey->get($key));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(MetIndicatorConfiguration $config): array
    {
        $indicator = $config->metIndicator;
        $center = $config->center;
        /** @var MetEstablishmentAgreement|null $agreement */
        $agreement = $config->relationLoaded('agentAgreement')
            ? $config->getRelation('agentAgreement')
            : null;

        $estado = $agreement?->estado;
        $estadoValue = $estado instanceof EstadoConvenioEstablecimiento ? $estado->value : null;
        $aplica = $config->aplica === null ? true : (bool) $config->aplica;
        $metaInstrumento = $this->decimalString($indicator?->meta_valor, 4);
        // Paridad GUI: si no aplica, no exponer meta comprometida/vigente (catálogo sigue en meta_instrumento).
        $metaComprometida = ($aplica && $config->meta_efectiva !== null)
            ? $this->decimalString($config->meta_efectiva, 4)
            : null;
        $metaVigente = $aplica ? ($metaComprometida ?? $metaInstrumento) : null;

        $unit = $config->measurementUnit;
        $fields = $indicator?->dataFields ?? collect();

        return [
            'cen_center_id' => (int) $config->cen_center_id,
            'establecimiento' => $center?->nombre_oficial ?? $center?->nombre_corto,
            'establecimiento_codigo' => $center?->codigo,
            'met_measurement_unit_id' => $config->met_measurement_unit_id !== null
                ? (int) $config->met_measurement_unit_id
                : null,
            'unidad_codigo' => $unit?->codigo,
            'unidad_nombre' => $unit?->nombre,
            'indicador_id' => (int) $config->met_indicator_id,
            'clave' => $indicator?->clave,
            'nombre' => $indicator?->nombre,
            'ley' => $this->leyFromIndicator($indicator),
            'estado_convenio' => $estadoValue,
            'estado_convenio_label' => $estado instanceof EstadoConvenioEstablecimiento ? $estado->label() : null,
            'submitted_at' => $agreement?->submitted_at?->toIso8601String(),
            'submitted_by' => $this->mapSubmittedBy($agreement?->submittedBy),
            'aplica' => $aplica,
            'agrupa_ss' => (bool) $config->agrupa_ss,
            'comprometido' => (bool) $config->seleccionado,
            'en_convenio' => $estado === EstadoConvenioEstablecimiento::Enviado,
            'meta_instrumento' => $metaInstrumento,
            'meta_comprometida' => $metaComprometida,
            'meta_vigente' => $metaVigente,
            'justificacion' => null,
            'ponderacion' => $config->ponderacion !== null
                ? $this->decimalString($config->ponderacion, 2)
                : null,
            'formula_descripcion' => $indicator?->formula?->description,
            'fuente_numerador' => $this->fuenteForComponente($fields, DataFieldComponente::Numerador),
            'fuente_denominador' => $this->fuenteForComponente($fields, DataFieldComponente::Denominador),
            'periodicidad' => $this->resolvePeriodicidad($indicator, $config),
            'campos_datos' => $this->mapDataFields($fields),
        ];
    }

    /**
     * Sobre vacío cuando el establecimiento no pertenece al SS del token.
     *
     * @return array{data: list<mixed>, meta: array<string, mixed>}
     */
    public function fueraDeAlcance(int $perPage, int $establecimientoId): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
                'empty_reason' => AgentPagination::EMPTY_REASON_FUERA_DE_ALCANCE,
                'establecimiento_id' => $establecimientoId,
            ],
        ];
    }

    /**
     * @param  Collection<int, MetDataField>|iterable<int, MetDataField>  $fields
     * @return list<array{label: mixed, variable_key: mixed, role: mixed, input_type: mixed, orden: int}>
     */
    private function mapDataFields(iterable $fields): array
    {
        $rows = [];
        foreach ($fields as $field) {
            $role = $field->componente instanceof DataFieldComponente
                ? $field->componente->value
                : $field->componente;

            $inputType = $field->input_type;
            $rows[] = [
                'label' => $field->label,
                'variable_key' => $field->variable_key,
                'role' => $role,
                'input_type' => $inputType instanceof \BackedEnum ? $inputType->value : $inputType,
                'orden' => (int) $field->orden,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, MetDataField>  $fields
     */
    private function fuenteForComponente(Collection $fields, DataFieldComponente $componente): ?string
    {
        $fuentes = $fields
            ->filter(function (MetDataField $field) use ($componente): bool {
                $value = $field->componente instanceof DataFieldComponente
                    ? $field->componente
                    : DataFieldComponente::tryFrom((string) $field->componente);

                return $value === $componente;
            })
            ->map(fn(MetDataField $field) => is_string($field->fuente) ? trim($field->fuente) : '')
            ->filter(fn(string $fuente) => $fuente !== '')
            ->unique()
            ->values();

        if ($fuentes->isEmpty()) {
            return null;
        }

        return $fuentes->implode(' | ');
    }

    private function resolvePeriodicidad(?MetIndicator $indicator, MetIndicatorConfiguration $config): string
    {
        if (! $indicator instanceof MetIndicator) {
            return MetIndicatorPeriodicityResolver::DEFAULT_PERIODICIDAD;
        }

        return $this->periodicityResolver
            ->resolveForCarga(
                $indicator,
                (int) $config->cen_hie_dependency_id,
                (int) $config->year_id
            )
            ->value;
    }

    /**
     * @return array{id: int, nombre: string}|null
     */
    private function mapSubmittedBy(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'nombre' => (string) $user->name,
        ];
    }

    private function leyFromIndicator(?MetIndicator $indicator): ?string
    {
        if ($indicator === null) {
            return null;
        }

        $route = $indicator->indicatorType?->route;
        if (! is_string($route)) {
            return null;
        }

        return match ($route) {
            'metas-18834' => '18834',
            'metas-19664' => '19664',
            'metas-20707' => '20707',
            default => null,
        };
    }

    private function decimalString(mixed $value, int $scale): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $scale, '.', '');
    }

    private function agreementKey(int $centerId, int $typeId): string
    {
        return $centerId . ':' . $typeId;
    }

    /**
     * @return list<int>
     */
    private function typeIdsForLey(string $ley): array
    {
        $normalized = preg_replace('/\D+/', '', $ley) ?? '';
        $route = match ($normalized) {
            '18834' => 'metas-18834',
            '19664' => 'metas-19664',
            '20707' => 'metas-20707',
            default => null,
        };

        if ($route === null || ! $this->metas->supportsLey($normalized)) {
            return [];
        }

        return IndicatorType::query()
            ->where('estado', true)
            ->where('route', $route)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();
    }

    /**
     * @param  Builder<MetIndicatorConfiguration>  $query
     * @param  list<int>  $typeIds
     */
    private function applyEstadoFilter(Builder $query, Year $year, array $typeIds, string $estado): void
    {
        $normalized = strtoupper($estado);

        if ($normalized === EstadoConvenioEstablecimiento::Enviado->value) {
            $query->whereExists(function ($sub) use ($year, $typeIds): void {
                $sub->selectRaw('1')
                    ->from('met_establishment_agreements as mea')
                    ->whereColumn('mea.cen_center_id', 'met_indicator_configurations.cen_center_id')
                    ->whereColumn('mea.cen_hie_dependency_id', 'met_indicator_configurations.cen_hie_dependency_id')
                    ->where('mea.year_id', $year->id)
                    ->whereIn('mea.indicator_type_id', $typeIds)
                    ->where('mea.estado', EstadoConvenioEstablecimiento::Enviado->value)
                    ->whereRaw('mea.indicator_type_id = (
                        select mi.indicator_type_id from met_indicators mi
                        where mi.id = met_indicator_configurations.met_indicator_id
                        limit 1
                    )');
            });

            return;
        }

        if ($normalized === EstadoConvenioEstablecimiento::Borrador->value) {
            // Solo fila explícita BORRADOR. Sin convenio (null) no entra: alinea campo y filtro.
            $query->whereExists(function ($sub) use ($year, $typeIds): void {
                $sub->selectRaw('1')
                    ->from('met_establishment_agreements as mea')
                    ->whereColumn('mea.cen_center_id', 'met_indicator_configurations.cen_center_id')
                    ->whereColumn('mea.cen_hie_dependency_id', 'met_indicator_configurations.cen_hie_dependency_id')
                    ->where('mea.year_id', $year->id)
                    ->whereIn('mea.indicator_type_id', $typeIds)
                    ->where('mea.estado', EstadoConvenioEstablecimiento::Borrador->value)
                    ->whereRaw('mea.indicator_type_id = (
                        select mi.indicator_type_id from met_indicators mi
                        where mi.id = met_indicator_configurations.met_indicator_id
                        limit 1
                    )');
            });
        }
    }
}
