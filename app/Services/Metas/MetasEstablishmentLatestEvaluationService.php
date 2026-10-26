<?php

namespace App\Services\Metas;

use App\Enums\IndicatorType\EtapaVigente;
use App\Models\IndicatorType;
use App\Models\MetCenterInclusion;
use App\Models\MetEstablishmentResult;
use App\Models\Year;
use Illuminate\Support\Collection;

/**
 * Promedio de última evaluación por establecimiento (tab resultados por establecimiento Metas).
 */
class MetasEstablishmentLatestEvaluationService
{
    public function averageLatestGlobalPercent(IndicatorType $type, Year $year, int $hieId): ?float
    {
        $latestValues = array_values(array_filter(
            $this->latestGlobalByCenter($type, $year, $hieId),
            static fn (?float $value): bool => $value !== null,
        ));

        if ($latestValues === []) {
            return null;
        }

        return round(array_sum($latestValues) / count($latestValues), 1);
    }

    /**
     * Establecimientos incluidos para evaluación Metas en la SS y año (unión de inclusiones y resultados).
     *
     * @return Collection<int, int>
     */
    public function evaluatedCenterIds(IndicatorType $type, Year $year, int $hieId): Collection
    {
        $includedCenterIds = MetCenterInclusion::query()
            ->where('year_id', $year->id)
            ->where('cen_hie_dependency_id', $hieId)
            ->where('indicator_type_id', $type->id)
            ->pluck('cen_center_id');

        $resultCenterIds = MetEstablishmentResult::query()
            ->where('year_id', $year->id)
            ->where('cen_hie_dependency_id', $hieId)
            ->where('indicator_type_id', $type->id)
            ->where('periodo', 'ANUAL')
            ->distinct()
            ->pluck('cen_center_id');

        return $includedCenterIds->merge($resultCenterIds)->unique()->values();
    }

    /**
     * Todos los establecimientos evaluados en Metas para la SS y año (unión de todos los instrumentos).
     *
     * @return Collection<int, int>
     */
    public function allEvaluatedCenterIds(Year $year, int $hieId): Collection
    {
        $includedCenterIds = MetCenterInclusion::query()
            ->where('year_id', $year->id)
            ->where('cen_hie_dependency_id', $hieId)
            ->pluck('cen_center_id');

        $resultCenterIds = MetEstablishmentResult::query()
            ->where('year_id', $year->id)
            ->where('cen_hie_dependency_id', $hieId)
            ->where('periodo', 'ANUAL')
            ->distinct()
            ->pluck('cen_center_id');

        return $includedCenterIds->merge($resultCenterIds)->unique()->values();
    }

    /**
     * Última evaluación global por establecimiento (final → preliminar → autoevaluación, periodo ANUAL).
     *
     * @return array<int, float|null>
     */
    public function latestGlobalByCenter(IndicatorType $type, Year $year, int $hieId): array
    {
        $centerIds = $this->evaluatedCenterIds($type, $year, $hieId);
        if ($centerIds->isEmpty()) {
            return [];
        }

        $autoByCenter = $this->annualGlobalByCenter(
            $this->annualResultsQuery($type, $year, $hieId, EtapaVigente::Autoevaluacion)->get(),
        );
        $prelimByCenter = $this->annualGlobalByCenter(
            $this->annualResultsQuery($type, $year, $hieId, EtapaVigente::Preliminar)->get(),
        );
        $finalByCenter = $this->annualGlobalByCenter(
            $this->annualResultsQuery($type, $year, $hieId, EtapaVigente::Final)->get(),
        );

        $latestByCenter = [];
        foreach ($centerIds as $centerId) {
            $latestByCenter[(int) $centerId] = $this->resolveLatestGlobalResult([
                EtapaVigente::Autoevaluacion->value => $autoByCenter[$centerId] ?? null,
                EtapaVigente::Preliminar->value => $prelimByCenter[$centerId] ?? null,
                EtapaVigente::Final->value => $finalByCenter[$centerId] ?? null,
            ]);
        }

        return $latestByCenter;
    }

    /**
     * @param  array<string, float|null>  $globalsByEtapa
     */
    private function resolveLatestGlobalResult(array $globalsByEtapa): ?float
    {
        foreach ([
            EtapaVigente::Final->value,
            EtapaVigente::Preliminar->value,
            EtapaVigente::Autoevaluacion->value,
        ] as $etapa) {
            if (isset($globalsByEtapa[$etapa]) && $globalsByEtapa[$etapa] !== null) {
                return (float) $globalsByEtapa[$etapa];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, MetEstablishmentResult>  $results
     * @return array<int, float>
     */
    private function annualGlobalByCenter(Collection $results): array
    {
        $byCenter = [];
        foreach ($results as $result) {
            if ($result->resultado_global !== null) {
                $byCenter[$result->cen_center_id] = (float) $result->resultado_global;
            }
        }

        return $byCenter;
    }

    private function annualResultsQuery(
        IndicatorType $type,
        Year $year,
        int $hieId,
        EtapaVigente $etapa,
    ) {
        return MetEstablishmentResult::query()
            ->where('year_id', $year->id)
            ->where('cen_hie_dependency_id', $hieId)
            ->where('indicator_type_id', $type->id)
            ->where('periodo', 'ANUAL')
            ->where('etapa', $etapa->value);
    }
}
