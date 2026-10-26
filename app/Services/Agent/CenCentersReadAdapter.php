<?php

namespace App\Services\Agent;

use App\Enums\Cen\CenCenterType;
use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Models\CenCenter;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class CenCentersReadAdapter
{
    /**
     * Alias cortos → glosa canónica de cen_type.
     *
     * @var array<string, string>
     */
    public const TIPO_ALIASES = [
        'SAR' => 'Servicio de Atención Primaria de Urgencia de Alta Resolutividad (SAR)',
        'SAPU' => 'Servicio de Atención Primaria de Urgencia (SAPU)',
        'SUR' => 'Servicio de Urgencia Rural (SUR)',
    ];

    /**
     * @return list<string>
     */
    public static function vigenteEstados(): array
    {
        return [
            CentroEstadoFuncionamiento::VigenteEnOperacionHabitual->value,
            CentroEstadoFuncionamiento::VigenteEnOperacionTransitoria->value,
        ];
    }

    /**
     * Resuelve filtro tipo: null = sin filtro; string = glosa a aplicar.
     *
     * @throws InvalidArgumentException alias o glosa desconocida
     */
    public function resolveTipoFilter(?string $tipo): ?string
    {
        if ($tipo === null || $tipo === '') {
            return null;
        }

        $trimmed = trim($tipo);
        $upper = strtoupper($trimmed);
        if (isset(self::TIPO_ALIASES[$upper])) {
            return self::TIPO_ALIASES[$upper];
        }

        foreach (CenCenterType::cases() as $case) {
            if ($case->value === $trimmed) {
                return $case->value;
            }
        }

        throw new InvalidArgumentException(
            'Parámetro tipo inválido. Use alias SAR|SAPU|SUR o la glosa exacta de tipo de establecimiento.'
        );
    }

    /**
     * @return LengthAwarePaginator<int, CenCenter>
     */
    public function paginate(
        int $ssId,
        ?string $tipoGlosa,
        ?bool $vigente,
        ?string $estadoFuncionamiento,
        ?string $q,
        ?string $comuna,
        int $perPage,
        int $page = 1,
    ): LengthAwarePaginator {
        $query = $this->baseQuery($ssId);

        if ($tipoGlosa !== null) {
            $query->where('cen_type', $tipoGlosa);
        }

        if ($vigente === true) {
            $query->whereIn('estado_funcionamiento', self::vigenteEstados());
        } elseif ($estadoFuncionamiento !== null && $estadoFuncionamiento !== '') {
            $query->where('estado_funcionamiento', $estadoFuncionamiento);
        }

        if ($q !== null && $q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $b) use ($like): void {
                $b->where('codigo', 'like', $like)
                    ->orWhere('nombre_corto', 'like', $like)
                    ->orWhere('nombre_oficial', 'like', $like);
            });
        }

        if ($comuna !== null && $comuna !== '') {
            $like = '%'.$comuna.'%';
            $query->whereHas('cenAddress.cenCommune', function (Builder $b) use ($like, $comuna): void {
                $b->where('nombre', 'like', $like)
                    ->orWhere('codigo', $comuna);
            });
        }

        return $query
            ->orderBy('nombre_corto')
            ->orderBy('codigo')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function findForSs(int $ssId, int $id): ?CenCenter
    {
        return $this->baseQuery($ssId)->whereKey($id)->first();
    }

    /**
     * @return Builder<CenCenter>
     */
    private function baseQuery(int $ssId): Builder
    {
        return CenCenter::query()
            ->where('cen_hie_dependency_id', $ssId)
            ->with([
                'cenAddress.cenCommune.cenProvince.cenRegion',
                'cenHieDependency',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function mapEstablecimiento(CenCenter $center): array
    {
        $address = $center->cenAddress;
        $commune = $address?->cenCommune;
        $region = $commune?->cenProvince?->cenRegion;
        $dependency = $center->cenHieDependency;

        return [
            'id' => (int) $center->getKey(),
            'codigo' => $center->codigo,
            'codigo_antiguo' => $center->cod_antiguo,
            'codigo_madre' => $center->codigo_madre,
            'codigo_madre_antiguo' => $center->cod_madre_antiguo,
            'nombre' => $center->nombre_oficial,
            'nombre_corto' => $center->nombre_corto,
            'tipo' => $this->enumValue($center->cen_type),
            'ambito' => $this->enumValue($center->cen_scope),
            'pertenencia_snss' => $this->enumValue($center->pertenencia_snss),
            'certificacion' => $this->enumValue($center->cen_certification),
            'dependencia_administrativa' => $this->enumValue($center->cen_adm_dependency),
            'nivel_atencion' => $this->enumValue($center->nivel_atencion),
            'tiene_urgencia' => $this->enumValue($center->tiene_urgencia),
            'tipo_urgencia' => $this->enumValue($center->cen_emergency_type),
            'tipo_sapu' => $this->enumValue($center->tipo_sapu),
            'tipo_sistema_salud' => $this->enumValue($center->cen_provider_type),
            'estado_funcionamiento' => $this->enumValue($center->estado_funcionamiento),
            'nivel_complejidad' => $this->enumValue($center->nivel_complejidad),
            'tipo_atencion' => $this->enumValue($center->tipo_atencion),
            'origen' => $this->enumValue($center->origen),
            'ss_codigo' => $dependency?->codigo,
            'ss_nombre' => $dependency?->nombre,
            'region_codigo' => $region?->codigo,
            'region_nombre' => $region?->nombre,
            'comuna_codigo' => $commune?->codigo,
            'comuna_nombre' => $commune?->nombre,
            'via' => $this->enumValue($address?->via),
            'numero' => $address?->numero,
            'nombre_via' => $address?->direccion,
            'telefono' => $address?->telefono,
            'latitud' => $address?->latitud,
            'longitud' => $address?->longitud,
            'synced_at' => $this->iso($center->synced_at),
            'updated_at' => $this->iso($center->updated_at),
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
    }

    private function iso(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return null;
    }
}
