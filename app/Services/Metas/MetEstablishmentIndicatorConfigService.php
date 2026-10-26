<?php

namespace App\Services\Metas;

use App\Models\IndicatorTypeGlobalSetting;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMeasurementUnit;
use Illuminate\Support\Collection;

class MetEstablishmentIndicatorConfigService
{
    public const ISSUE_PONDERACION = 'ponderacion';

    public const ISSUE_META = 'meta';

    /**
     * @param  array<int, int>  $centerIds
     * @return Collection<int, array{
     *     types: list<string>,
     *     message: string,
     *     units?: list<array{id: int, codigo: string, nombre: ?string, types: list<string>, message: string}>
     * }>
     */
    public function issuesByCenterId(
        int $indicatorTypeId,
        array $centerIds,
        int $yearId,
        int $cenHieDependencyId,
        bool $usesInternalUnitMeasurement,
    ): Collection {
        if ($centerIds === []) {
            return collect();
        }

        $globalSetting = IndicatorTypeGlobalSetting::query()->firstOrCreate(
            [
                'indicator_type_id' => $indicatorTypeId,
                'year_id' => $yearId,
            ],
            [
                'min_indicadores' => 4,
                'min_ponderacion' => 0.00,
                'max_ponderacion' => 100.00,
            ],
        );

        $configsByScope = MetIndicatorConfiguration::query()
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->whereIn('cen_center_id', $centerIds)
            ->whereHas('metIndicator', fn ($query) => $query
                ->where('indicator_type_id', $indicatorTypeId)
                ->where('year_id', $yearId)
                ->where('estado', true))
            ->get()
            ->groupBy(fn (MetIndicatorConfiguration $config): string => $this->scopeKey(
                (int) $config->cen_center_id,
                $config->met_measurement_unit_id,
            ));

        $issues = collect();

        foreach ($centerIds as $centerId) {
            $issue = $this->issueForCenter(
                (int) $centerId,
                $configsByScope,
                $globalSetting,
                $yearId,
                $cenHieDependencyId,
                $usesInternalUnitMeasurement,
            );

            if ($issue !== null) {
                $issues->put((int) $centerId, $issue);
            }
        }

        return $issues;
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     codigo: string,
     *     nombre: ?string,
     *     is_complete: bool,
     *     types: list<string>,
     *     message: ?string
     * }>
     */
    public function unitStatusesForCenter(
        int $indicatorTypeId,
        int $centerId,
        int $yearId,
        int $cenHieDependencyId,
    ): Collection {
        $globalSetting = IndicatorTypeGlobalSetting::query()->firstOrCreate(
            [
                'indicator_type_id' => $indicatorTypeId,
                'year_id' => $yearId,
            ],
            [
                'min_indicadores' => 4,
                'min_ponderacion' => 0.00,
                'max_ponderacion' => 100.00,
            ],
        );

        $configsByScope = MetIndicatorConfiguration::query()
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->where('cen_center_id', $centerId)
            ->whereHas('metIndicator', fn ($query) => $query
                ->where('indicator_type_id', $indicatorTypeId)
                ->where('year_id', $yearId)
                ->where('estado', true))
            ->get()
            ->groupBy(fn (MetIndicatorConfiguration $config): string => $this->scopeKey(
                (int) $config->cen_center_id,
                $config->met_measurement_unit_id,
            ));

        $units = MetMeasurementUnit::query()
            ->where('cen_center_id', $centerId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return $units->map(function (MetMeasurementUnit $unit) use ($centerId, $configsByScope, $globalSetting): array {
            $scopeIssue = $this->issueForScope(
                $configsByScope->get($this->scopeKey($centerId, (int) $unit->id), collect()),
                $globalSetting,
            );

            return [
                'id' => (int) $unit->id,
                'codigo' => $unit->codigo,
                'nombre' => $unit->nombre,
                'is_complete' => $scopeIssue === null,
                'types' => $scopeIssue['types'] ?? [],
                'message' => $scopeIssue['message'] ?? null,
            ];
        });
    }

    /**
     * @param  Collection<string, Collection<int, MetIndicatorConfiguration>>  $configsByScope
     * @return array{
     *     types: list<string>,
     *     message: string,
     *     units?: list<array{id: int, codigo: string, nombre: ?string, types: list<string>, message: string}>
     * }|null
     */
    private function issueForCenter(
        int $centerId,
        Collection $configsByScope,
        IndicatorTypeGlobalSetting $globalSetting,
        int $yearId,
        int $cenHieDependencyId,
        bool $usesInternalUnitMeasurement,
    ): ?array {
        if ($usesInternalUnitMeasurement) {
            $measurementUnits = MetMeasurementUnit::query()
                ->where('cen_center_id', $centerId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $cenHieDependencyId)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']);

            if ($measurementUnits->isEmpty()) {
                return [
                    'types' => [self::ISSUE_PONDERACION],
                    'message' => 'Sin unidades de medición registradas para este establecimiento.',
                    'units' => [],
                ];
            }

            $types = [];
            $unitIssues = [];

            foreach ($measurementUnits as $unit) {
                $scopeIssue = $this->issueForScope(
                    $configsByScope->get($this->scopeKey($centerId, (int) $unit->id), collect()),
                    $globalSetting,
                );

                if ($scopeIssue === null) {
                    continue;
                }

                $types = array_values(array_unique(array_merge($types, $scopeIssue['types'])));
                $unitIssues[] = [
                    'id' => (int) $unit->id,
                    'codigo' => $unit->codigo,
                    'nombre' => $unit->nombre,
                    'types' => $scopeIssue['types'],
                    'message' => $scopeIssue['message'],
                ];
            }

            if ($unitIssues === []) {
                return null;
            }

            $incompleteCodes = array_column($unitIssues, 'codigo');
            $count = count($incompleteCodes);
            $codesList = implode(', ', $incompleteCodes);
            $message = $count === 1
                ? "1 unidad incompleta: {$codesList}"
                : "{$count} unidades incompletas: {$codesList}";

            return [
                'types' => $types,
                'message' => $message,
                'units' => $unitIssues,
            ];
        }

        return $this->issueForScope(
            $configsByScope->get($this->scopeKey($centerId, null), collect()),
            $globalSetting,
        );
    }

    /**
     * @param  Collection<int, MetIndicatorConfiguration>  $configs
     * @return array{types: list<string>, message: string}|null
     */
    private function issueForScope(
        Collection $configs,
        IndicatorTypeGlobalSetting $globalSetting,
    ): ?array {
        $selected = $configs
            ->filter(fn (MetIndicatorConfiguration $config): bool => $config->seleccionado && $config->aplica !== false)
            ->values();

        if ($selected->isEmpty()) {
            return [
                'types' => [self::ISSUE_PONDERACION],
                'message' => 'Sin indicadores en convenio configurados.',
            ];
        }

        if ($selected->count() < (int) $globalSetting->min_indicadores) {
            return [
                'types' => [self::ISSUE_PONDERACION],
                'message' => 'Faltan indicadores en convenio (mínimo '.$globalSetting->min_indicadores.').',
            ];
        }

        foreach ($selected as $config) {
            if ($config->ponderacion === null) {
                return [
                    'types' => [self::ISSUE_PONDERACION],
                    'message' => 'Hay indicadores en convenio sin ponderación asignada.',
                ];
            }

            $ponderacion = (float) $config->ponderacion;

            if ($ponderacion < (float) $globalSetting->min_ponderacion) {
                return [
                    'types' => [self::ISSUE_PONDERACION],
                    'message' => 'Hay indicadores en convenio sin ponderación asignada.',
                ];
            }
        }

        $sumPonderacion = $selected->sum(fn (MetIndicatorConfiguration $config): float => (float) $config->ponderacion);

        if (abs($sumPonderacion - 100) > 0.001) {
            return [
                'types' => [self::ISSUE_PONDERACION],
                'message' => 'La suma de ponderaciones no es 100%.',
            ];
        }

        $missingMeta = $configs
            ->where('aplica', true)
            ->contains(fn (MetIndicatorConfiguration $config): bool => $config->meta_efectiva === null);

        if ($missingMeta) {
            return [
                'types' => [self::ISSUE_META],
                'message' => 'Hay indicadores con meta efectiva sin registrar.',
            ];
        }

        return null;
    }

    private function scopeKey(int $centerId, ?int $measurementUnitId): string
    {
        return $centerId.'|'.(int) ($measurementUnitId ?? 0);
    }
}
