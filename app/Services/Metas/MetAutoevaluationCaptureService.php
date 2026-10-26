<?php

namespace App\Services\Metas;

use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetResult;
use App\Support\Metas\MetIndicatorPrimaryComponentPresenter;

class MetAutoevaluationCaptureService
{
    /** @var array<string, int> */
    private const PERIODO_ORDER = [
        'MES-01' => 1,
        'MES-02' => 2,
        'MES-03' => 3,
        'MES-04' => 4,
        'MES-05' => 5,
        'MES-06' => 6,
        'MES-07' => 7,
        'MES-08' => 8,
        'MES-09' => 9,
        'MES-10' => 10,
        'MES-11' => 11,
        'MES-12' => 12,
        'TRIM-1' => 13,
        'TRIM-2' => 14,
        'TRIM-3' => 15,
        'TRIM-4' => 16,
        'SEM-1' => 17,
        'SEM-2' => 18,
        'ANUAL' => 19,
    ];

    /** @return array<int, array{numerador: float|null, denominador: float|null}> */
    public function capturesForCenter(
        int $indicatorTypeId,
        int $yearId,
        int $dependencyId,
        int $centerId,
    ): array {
        $configurations = MetIndicatorConfiguration::query()
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->where('cen_center_id', $centerId)
            ->where('seleccionado', true)
            ->where('aplica', true)
            ->whereNull('met_measurement_unit_id')
            ->whereHas('metIndicator', fn ($query) => $query->where('indicator_type_id', $indicatorTypeId))
            ->with('metIndicator.dataFields')
            ->get();

        $captures = [];

        foreach ($configurations as $configuration) {
            $captures[$configuration->met_indicator_id] = $this->resolveAnnualCapture(
                $configuration,
                $yearId,
                $dependencyId,
            );
        }

        return $captures;
    }

    /**
     * @return array{numerador: float|null, denominador: float|null}
     */
    private function resolveAnnualCapture(
        MetIndicatorConfiguration $configuration,
        int $yearId,
        int $dependencyId,
    ): array {
        $group = MetResult::query()
            ->where('met_indicator_id', $configuration->met_indicator_id)
            ->where('cen_center_id', $configuration->cen_center_id)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->whereNull('met_measurement_unit_id')
            ->etapaAutoevaluacion()
            ->get();

        if ($group->isEmpty()) {
            return ['numerador' => null, 'denominador' => null];
        }

        $indicator = $configuration->metIndicator;
        if (! $indicator) {
            $anual = $group->firstWhere('periodo', 'ANUAL');

            return [
                'numerador' => $anual?->numerador !== null ? (float) $anual->numerador : null,
                'denominador' => $anual?->denominador !== null ? (float) $anual->denominador : null,
            ];
        }

        $effectiveFields = app(MetFieldSsConfigService::class)->applyEffectiveTypes(
            $indicator->dataFields,
            $dependencyId,
            $yearId,
        );

        $summary = app(MetResultAggregationPresenter::class)->aggregatePeriodResultsToAnnualSummary(
            $group,
            $effectiveFields,
            $indicator->decimales ?? 2,
            $indicator->decimales_modo?->value ?? 'redondear',
            self::PERIODO_ORDER,
            $indicator,
            $configuration,
        );

        return $this->captureFromSummary($indicator, [
            'numerador' => $summary['numerador'],
            'denominador' => $summary['denominador'],
            'resultado' => $summary['resultado'],
        ]);
    }

    /**
     * @param  array{numerador: float|null, denominador: float|null, resultado: float|null}  $summary
     * @return array{numerador: float|null, denominador: float|null}
     */
    private function captureFromSummary(MetIndicator $indicator, array $summary): array
    {
        if (
            MetIndicatorPrimaryComponentPresenter::usesComplexPrimaryComponents($indicator->dataFields)
            && $summary['resultado'] !== null
        ) {
            return [
                'numerador' => $summary['resultado'],
                'denominador' => 100.0,
            ];
        }

        return [
            'numerador' => $summary['numerador'],
            'denominador' => $summary['denominador'],
        ];
    }
}
