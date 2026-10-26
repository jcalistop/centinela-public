<?php

namespace App\Services;

use App\Enums\DecimalesModo;
use App\Enums\MetaOperator;
use App\Models\IndicatorType;
use App\Models\MetEstablishmentResult;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMeasurementUnit;
use App\Models\MetResult;
use App\Services\Metas\MetFieldSsConfigService;
use App\Services\Metas\MetIndicatorEvaluationService;
use App\Services\Metas\MetResultAggregationPresenter;
use Illuminate\Support\Collection;

class MetCalculationService
{
    public const ETAPA_AUTOEVALUACION = 'AUTOEVALUACION';

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

    public function __construct(
        private readonly MetDataAggregatorService $dataAggregatorService,
        private readonly MetIndicatorEvaluationService $indicatorEvaluationService,
    ) {}

    public function calculateIndicatorPeriod(
        MetIndicator $indicator,
        int $centerId,
        int $yearId,
        int $dependencyId,
        string $periodo,
        array $adosadoCenterIds = [],
        ?int $measurementUnitId = null,
    ): MetResult {
        $variables = $this->dataAggregatorService->aggregateVariables(
            $indicator,
            $centerId,
            $yearId,
            $periodo,
            $adosadoCenterIds,
            $measurementUnitId,
            $dependencyId,
        );
        $primaryComponenteValues = $this->dataAggregatorService->resolvePrimaryComponenteValues($indicator, $variables);

        $evaluation = $this->indicatorEvaluationService->evaluate(
            $indicator,
            $periodo,
            $variables,
            $primaryComponenteValues['numerador'],
            $primaryComponenteValues['denominador'],
        );

        $resultado = $evaluation['ok'] ? $evaluation['result'] : null;
        $errorMessage = $evaluation['ok'] ? null : $evaluation['message'];

        $configQuery = MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $indicator->id)
            ->where('cen_center_id', $centerId)
            ->where('year_id', $yearId);

        if ($measurementUnitId !== null) {
            $configQuery->where('met_measurement_unit_id', $measurementUnitId);
        } else {
            $configQuery->whereNull('met_measurement_unit_id');
        }

        $config = $configQuery->first();

        $metaEfectiva = $config?->meta_efectiva !== null ? (float) $config->meta_efectiva : null;
        $cumpleMeta = $this->resolveCumpleMeta($indicator, $resultado, $metaEfectiva);

        return MetResult::updateOrCreate(
            [
                'met_indicator_id' => $indicator->id,
                'cen_center_id' => $centerId,
                'year_id' => $yearId,
                'periodo' => $periodo,
                'result_unit_scope_key' => (int) ($measurementUnitId ?? 0),
                'etapa' => self::ETAPA_AUTOEVALUACION,
            ],
            [
                'met_measurement_unit_id' => $measurementUnitId,
                'cen_hie_dependency_id' => $dependencyId,
                'numerador' => $primaryComponenteValues['numerador'],
                'denominador' => $primaryComponenteValues['denominador'],
                'resultado' => $resultado,
                'cumple_meta' => $cumpleMeta,
                'error_message' => $errorMessage,
            ]
        );
    }

    public function calculateIndicatorFromAnnualCapture(
        MetIndicator $indicator,
        int $centerId,
        int $yearId,
        int $dependencyId,
        string $etapa,
        ?float $numerador,
        ?float $denominador,
        ?int $measurementUnitId = null,
    ): MetResult {
        $evaluation = $this->evaluateAnnualCaptureQuotient($indicator, $numerador, $denominador);

        $resultado = $evaluation['ok'] ? $evaluation['result'] : null;
        $errorMessage = $evaluation['ok'] ? null : $evaluation['message'];

        $configQuery = MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $indicator->id)
            ->where('cen_center_id', $centerId)
            ->where('year_id', $yearId);

        if ($measurementUnitId !== null) {
            $configQuery->where('met_measurement_unit_id', $measurementUnitId);
        } else {
            $configQuery->whereNull('met_measurement_unit_id');
        }

        $config = $configQuery->first();

        $metaEfectiva = $config?->meta_efectiva !== null ? (float) $config->meta_efectiva : null;
        $cumpleMeta = $this->resolveCumpleMeta($indicator, $resultado, $metaEfectiva);

        return MetResult::updateOrCreate(
            [
                'met_indicator_id' => $indicator->id,
                'cen_center_id' => $centerId,
                'year_id' => $yearId,
                'periodo' => 'ANUAL',
                'result_unit_scope_key' => (int) ($measurementUnitId ?? 0),
                'etapa' => $etapa,
            ],
            [
                'met_measurement_unit_id' => $measurementUnitId,
                'cen_hie_dependency_id' => $dependencyId,
                'numerador' => $numerador,
                'denominador' => $denominador,
                'resultado' => $resultado,
                'cumple_meta' => $cumpleMeta,
                'error_message' => $errorMessage,
            ]
        );
    }

    /**
     * Captura anual preliminar/final: resultado = (numerador / denominador) × 100.
     * No usa `met_formulas` del indicador (enmienda SDD-066).
     *
     * @return array{ok: bool, result: float|null, message: string}
     */
    public function evaluateAnnualCaptureQuotient(
        MetIndicator $indicator,
        ?float $numerador,
        ?float $denominador,
    ): array {
        if ($numerador === null || $denominador === null) {
            return ['ok' => false, 'result' => null, 'message' => 'N/D'];
        }

        $epsilon = 0.0000001;
        $numIsZero = abs($numerador) < $epsilon;
        $denIsZero = abs($denominador) < $epsilon;

        if ($denIsZero) {
            if ($numIsZero) {
                return ['ok' => true, 'result' => 0.0, 'message' => 'OK'];
            }

            return ['ok' => false, 'result' => null, 'message' => MetResult::MESSAGE_DIVISOR_CERO];
        }

        $raw = ($numerador / $denominador) * 100.0;
        $decimals = max(0, min(10, (int) ($indicator->decimales ?? 2)));
        $modo = $indicator->decimales_modo ?? DecimalesModo::Redondear;
        if (! $modo instanceof DecimalesModo) {
            $modo = DecimalesModo::tryFrom((string) $modo) ?? DecimalesModo::Redondear;
        }

        return [
            'ok' => true,
            'result' => $this->applyCaptureDecimals($raw, $decimals, $modo),
            'message' => 'OK',
        ];
    }

    private function applyCaptureDecimals(float $value, int $decimals, DecimalesModo $mode): float
    {
        if ($mode === DecimalesModo::Truncar) {
            $factor = 10 ** $decimals;

            if ($value >= 0) {
                return floor($value * $factor) / $factor;
            }

            return ceil($value * $factor) / $factor;
        }

        return round($value, $decimals);
    }

    public function calculateEstablishmentResult(
        int $centerId,
        int $yearId,
        int $dependencyId,
        string $periodo,
        int $indicatorTypeId,
        string $etapa = self::ETAPA_AUTOEVALUACION,
    ): MetEstablishmentResult {
        $indicatorType = IndicatorType::query()->findOrFail($indicatorTypeId);

        if ($indicatorType->usesInternalUnitMeasurement()) {
            return $this->calculateEstablishmentResultForInternalUnits(
                $centerId,
                $yearId,
                $dependencyId,
                $periodo,
                $indicatorTypeId,
                $etapa,
            );
        }

        $selectedConfigurations = MetIndicatorConfiguration::query()
            ->where('year_id', $yearId)
            ->where('cen_center_id', $centerId)
            ->whereNull('met_measurement_unit_id')
            ->where('seleccionado', true)
            ->whereHas('metIndicator', function ($q) use ($indicatorTypeId): void {
                $q->where('indicator_type_id', $indicatorTypeId);
            })
            ->with('metIndicator')
            ->get();

        return $this->persistEstablishmentAggregate(
            $centerId,
            $yearId,
            $dependencyId,
            $periodo,
            $indicatorTypeId,
            $selectedConfigurations,
            $etapa,
        );
    }

    /**
     * @return array{resultado_global: float|null, n_seleccionados: int, n_calculados: int}
     */
    public function sumWeightedComplianceForEstablishment(
        int $centerId,
        int $yearId,
        int $dependencyId,
        string $periodo,
        int $indicatorTypeId,
        string $etapa = self::ETAPA_AUTOEVALUACION,
    ): array {
        $indicatorType = IndicatorType::query()->findOrFail($indicatorTypeId);

        if ($indicatorType->usesInternalUnitMeasurement()) {
            return $this->sumWeightedComplianceForInternalUnits(
                $centerId,
                $yearId,
                $dependencyId,
                $periodo,
                $indicatorTypeId,
                $etapa,
            );
        }

        $selectedConfigurations = MetIndicatorConfiguration::query()
            ->where('year_id', $yearId)
            ->where('cen_center_id', $centerId)
            ->whereNull('met_measurement_unit_id')
            ->where('seleccionado', true)
            ->whereHas('metIndicator', function ($q) use ($indicatorTypeId): void {
                $q->where('indicator_type_id', $indicatorTypeId);
            })
            ->with('metIndicator')
            ->get();

        return $this->aggregateWeightedCompliance($selectedConfigurations, $centerId, $yearId, $dependencyId, $periodo, $etapa);
    }

    /**
     * @return array{resultado_global: float|null, n_seleccionados: int, n_calculados: int}
     */
    public function sumWeightedComplianceForUnit(
        int $centerId,
        int $yearId,
        int $dependencyId,
        string $periodo,
        int $indicatorTypeId,
        int $measurementUnitId,
        string $etapa = self::ETAPA_AUTOEVALUACION,
    ): array {
        $selectedConfigurations = MetIndicatorConfiguration::query()
            ->where('year_id', $yearId)
            ->where('cen_center_id', $centerId)
            ->where('met_measurement_unit_id', $measurementUnitId)
            ->where('seleccionado', true)
            ->whereHas('metIndicator', function ($q) use ($indicatorTypeId): void {
                $q->where('indicator_type_id', $indicatorTypeId);
            })
            ->with('metIndicator')
            ->get();

        return $this->aggregateWeightedCompliance(
            $selectedConfigurations,
            $centerId,
            $yearId,
            $dependencyId,
            $periodo,
            $etapa,
            $measurementUnitId,
        );
    }

    /**
     * Cumplimiento global por unidad y etapa en lote (evita N+1 de configuraciones en tab Resultados).
     *
     * @param  Collection<int, MetMeasurementUnit>  $units
     * @param  list<string>  $etapas
     * @return array<int, array<int, array<string, float|null>>>
     */
    public function weightedGlobalResultsByCenterUnitAndEtapa(
        Collection $units,
        int $yearId,
        int $dependencyId,
        int $indicatorTypeId,
        string $periodo,
        array $etapas,
    ): array {
        if ($units->isEmpty()) {
            return [];
        }

        $centerIds = $units->pluck('cen_center_id')->unique()->values();
        $unitIds = $units->pluck('id')->unique()->values();

        $configurationsByCenterUnit = MetIndicatorConfiguration::query()
            ->where('year_id', $yearId)
            ->whereIn('cen_center_id', $centerIds)
            ->whereIn('met_measurement_unit_id', $unitIds)
            ->where('seleccionado', true)
            ->whereHas('metIndicator', function ($q) use ($indicatorTypeId): void {
                $q->where('indicator_type_id', $indicatorTypeId);
            })
            ->with('metIndicator')
            ->get()
            ->groupBy(fn (MetIndicatorConfiguration $configuration): string => $configuration->cen_center_id.'-'.$configuration->met_measurement_unit_id);

        $results = [];

        foreach ($units as $unit) {
            $configKey = $unit->cen_center_id.'-'.$unit->id;
            $selectedConfigurations = $configurationsByCenterUnit->get($configKey, collect());

            foreach ($etapas as $etapa) {
                $aggregate = $this->aggregateWeightedCompliance(
                    $selectedConfigurations,
                    $unit->cen_center_id,
                    $yearId,
                    $dependencyId,
                    $periodo,
                    $etapa,
                    $unit->id,
                );

                $results[$unit->cen_center_id][$unit->id][$etapa] = $aggregate['resultado_global'];
            }
        }

        return $results;
    }

    /**
     * Cumplimiento visible: lineal respecto de la meta, acotado a [0 %, 100 %], sin umbrales.
     * Polaridad según meta_operador: >=/>/= → resultado/meta; <=/< → meta/resultado (SDD-186 / SDD-195 / SDD-200).
     */
    public function indicatorCompliancePercent(
        ?MetIndicator $indicator,
        float $resultado,
        ?float $metaEfectiva,
    ): ?float {
        if ($metaEfectiva === null || $metaEfectiva <= 0) {
            return null;
        }

        if ($this->indicatorPrefersLowerValues($indicator)) {
            if ($resultado <= 0.0) {
                return 100.0;
            }

            return max(0.0, min(100.0, ($metaEfectiva / $resultado) * 100));
        }

        return max(0.0, min(100.0, ($resultado / $metaEfectiva) * 100));
    }

    /**
     * Cumplimiento escalado por los umbrales del indicador. Solo alimenta el aporte ponderado (SDD-186).
     */
    public function indicatorScaledCompliancePercent(
        ?MetIndicator $indicator,
        float $resultado,
        ?float $metaEfectiva,
    ): ?float {
        $cumplimiento = $this->indicatorCompliancePercent($indicator, $resultado, $metaEfectiva);

        if ($cumplimiento === null) {
            return null;
        }

        return $this->resolveScaledCompliance(min(100.0, $cumplimiento), $indicator);
    }

    public function weightedComplianceFromResult(
        MetIndicatorConfiguration $configuration,
        float $resultado,
    ): ?float {
        $metaEfectiva = $configuration->meta_efectiva !== null
            ? (float) $configuration->meta_efectiva
            : ($configuration->metIndicator?->meta_valor !== null ? (float) $configuration->metIndicator->meta_valor : null);

        $cumplimiento = $this->indicatorScaledCompliancePercent(
            $configuration->metIndicator,
            $resultado,
            $metaEfectiva,
        );

        if ($cumplimiento === null) {
            return null;
        }

        $ponderacion = (float) ($configuration->ponderacion ?? 0);

        return round($ponderacion * ($cumplimiento / 100), 2);
    }

    /**
     * @param  Collection<int, MetIndicatorConfiguration>  $selectedConfigurations
     * @return array{resultado_global: float|null, n_seleccionados: int, n_calculados: int}
     */
    private function aggregateWeightedCompliance(
        Collection $selectedConfigurations,
        int $centerId,
        int $yearId,
        int $dependencyId,
        string $periodo,
        string $etapa,
        ?int $measurementUnitId = null,
    ): array {
        $nSeleccionados = $selectedConfigurations->count();
        $nCalculados = 0;
        $resultadoGlobal = 0.0;

        foreach ($selectedConfigurations as $configuration) {
            $contribucion = $this->weightedComplianceContribution(
                $configuration,
                $centerId,
                $yearId,
                $dependencyId,
                $periodo,
                $etapa,
                $measurementUnitId,
            );

            if ($contribucion === null) {
                continue;
            }

            $resultadoGlobal += $contribucion;
            $nCalculados++;
        }

        return [
            'resultado_global' => $nCalculados > 0 ? $resultadoGlobal : null,
            'n_seleccionados' => $nSeleccionados,
            'n_calculados' => $nCalculados,
        ];
    }

    /**
     * @return array{resultado_global: float|null, n_seleccionados: int, n_calculados: int}
     */
    private function sumWeightedComplianceForInternalUnits(
        int $centerId,
        int $yearId,
        int $dependencyId,
        string $periodo,
        int $indicatorTypeId,
        string $etapa,
    ): array {
        $unitIds = MetIndicatorConfiguration::query()
            ->where('year_id', $yearId)
            ->where('cen_center_id', $centerId)
            ->where('seleccionado', true)
            ->whereNotNull('met_measurement_unit_id')
            ->whereHas('metIndicator', function ($q) use ($indicatorTypeId): void {
                $q->where('indicator_type_id', $indicatorTypeId);
            })
            ->pluck('met_measurement_unit_id')
            ->unique()
            ->filter()
            ->values();

        $partialGlobals = [];
        $nSeleccionadosTotal = 0;

        foreach ($unitIds as $unitId) {
            $selectedConfigurations = MetIndicatorConfiguration::query()
                ->where('year_id', $yearId)
                ->where('cen_center_id', $centerId)
                ->where('met_measurement_unit_id', $unitId)
                ->where('seleccionado', true)
                ->whereHas('metIndicator', function ($q) use ($indicatorTypeId): void {
                    $q->where('indicator_type_id', $indicatorTypeId);
                })
                ->with('metIndicator')
                ->get();

            $nSeleccionadosTotal += $selectedConfigurations->count();

            $aggregate = $this->aggregateWeightedCompliance(
                $selectedConfigurations,
                $centerId,
                $yearId,
                $dependencyId,
                $periodo,
                $etapa,
                $unitId,
            );

            if ($aggregate['n_calculados'] > 0 && $aggregate['resultado_global'] !== null) {
                $partialGlobals[] = $aggregate['resultado_global'];
            }
        }

        $nUnidadesPromediadas = count($partialGlobals);

        return [
            'resultado_global' => $nUnidadesPromediadas > 0
                ? array_sum($partialGlobals) / $nUnidadesPromediadas
                : null,
            'n_seleccionados' => $nSeleccionadosTotal,
            'n_calculados' => $nUnidadesPromediadas,
        ];
    }

    /**
     * @param  Collection<int, MetIndicatorConfiguration>  $selectedConfigurations
     */
    private function persistEstablishmentAggregate(
        int $centerId,
        int $yearId,
        int $dependencyId,
        string $periodo,
        int $indicatorTypeId,
        Collection $selectedConfigurations,
        string $etapa = self::ETAPA_AUTOEVALUACION,
    ): MetEstablishmentResult {
        $aggregate = $this->aggregateWeightedCompliance(
            $selectedConfigurations,
            $centerId,
            $yearId,
            $dependencyId,
            $periodo,
            $etapa,
        );

        return MetEstablishmentResult::updateOrCreate(
            [
                'cen_center_id' => $centerId,
                'year_id' => $yearId,
                'periodo' => $periodo,
                'indicator_type_id' => $indicatorTypeId,
                'etapa' => $etapa,
            ],
            [
                'cen_hie_dependency_id' => $dependencyId,
                'resultado_global' => $aggregate['resultado_global'],
                'n_seleccionados' => $aggregate['n_seleccionados'],
                'n_calculados' => $aggregate['n_calculados'],
            ]
        );
    }

    private function calculateEstablishmentResultForInternalUnits(
        int $centerId,
        int $yearId,
        int $dependencyId,
        string $periodo,
        int $indicatorTypeId,
        string $etapa = self::ETAPA_AUTOEVALUACION,
    ): MetEstablishmentResult {
        $aggregate = $this->sumWeightedComplianceForInternalUnits(
            $centerId,
            $yearId,
            $dependencyId,
            $periodo,
            $indicatorTypeId,
            $etapa,
        );

        return MetEstablishmentResult::updateOrCreate(
            [
                'cen_center_id' => $centerId,
                'year_id' => $yearId,
                'periodo' => $periodo,
                'indicator_type_id' => $indicatorTypeId,
                'etapa' => $etapa,
            ],
            [
                'cen_hie_dependency_id' => $dependencyId,
                'resultado_global' => $aggregate['resultado_global'],
                'n_seleccionados' => $aggregate['n_seleccionados'],
                'n_calculados' => $aggregate['n_calculados'],
            ]
        );
    }

    private function weightedComplianceContribution(
        MetIndicatorConfiguration $configuration,
        int $centerId,
        int $yearId,
        int $dependencyId,
        string $periodo,
        string $etapa,
        ?int $measurementUnitId = null,
    ): ?float {
        $resultado = $this->resolveIndicatorResultForEstablishmentPeriod(
            $configuration,
            $centerId,
            $yearId,
            $dependencyId,
            $periodo,
            $etapa,
            $measurementUnitId,
        );

        if ($resultado === null) {
            return null;
        }

        return $this->weightedComplianceFromResult($configuration, $resultado);
    }

    private function resolveIndicatorResultForEstablishmentPeriod(
        MetIndicatorConfiguration $configuration,
        int $centerId,
        int $yearId,
        int $dependencyId,
        string $periodo,
        string $etapa,
        ?int $measurementUnitId = null,
    ): ?float {
        $query = MetResult::query()
            ->where('met_indicator_id', $configuration->met_indicator_id)
            ->where('cen_center_id', $centerId)
            ->where('year_id', $yearId)
            ->where('etapa', $etapa);

        if ($measurementUnitId !== null) {
            $query->where('met_measurement_unit_id', $measurementUnitId);
        } else {
            $query->whereNull('met_measurement_unit_id');
        }

        if ($periodo !== 'ANUAL') {
            $result = $query->where('periodo', $periodo)->first();

            return $result?->resultado !== null ? (float) $result->resultado : null;
        }

        $group = $query->get();
        if ($group->isEmpty()) {
            return null;
        }

        $indicator = $configuration->metIndicator;
        if (! $indicator) {
            return null;
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

        if ($summary['error_message'] !== null || $summary['resultado'] === null) {
            return null;
        }

        return (float) $summary['resultado'];
    }

    /**
     * ¿El resultado cumple la meta del indicador según meta_operador? (null si no evaluable).
     */
    public function indicatorMeetsMeta(
        MetIndicator $indicator,
        ?float $resultado,
        ?float $metaEfectiva = null,
    ): ?bool {
        return $this->resolveCumpleMeta($indicator, $resultado, $metaEfectiva);
    }

    private function resolveCumpleMeta(MetIndicator $indicator, ?float $resultado, ?float $metaEfectiva = null): ?bool
    {
        if ($indicator->meta_reglas !== null) {
            return null;
        }

        $metaValor = $metaEfectiva ?? ($indicator->meta_valor !== null ? (float) $indicator->meta_valor : null);

        if ($resultado === null || $metaValor === null || $indicator->meta_operador === null) {
            return null;
        }

        return match ($indicator->meta_operador) {
            MetaOperator::MayorIgual => $resultado >= $metaValor,
            MetaOperator::Mayor => $resultado > $metaValor,
            MetaOperator::MenorIgual => $resultado <= $metaValor,
            MetaOperator::Menor => $resultado < $metaValor,
            MetaOperator::Igual => abs($resultado - $metaValor) < 0.000001,
        };
    }

    private function indicatorPrefersLowerValues(?MetIndicator $indicator): bool
    {
        return match ($indicator?->meta_operador) {
            MetaOperator::MenorIgual, MetaOperator::Menor => true,
            default => false,
        };
    }

    private function resolveScaledCompliance(float $cumplimiento, ?MetIndicator $indicator): float
    {
        $umbralInferior = $indicator?->umbral_inferior !== null ? (float) $indicator->umbral_inferior : 0.0;
        $umbralSuperior = $indicator?->umbral_superior !== null ? (float) $indicator->umbral_superior : 100.0;

        if ($cumplimiento < $umbralInferior) {
            return 0.0;
        }

        if ($cumplimiento >= $umbralSuperior) {
            return 100.0;
        }

        return $cumplimiento;
    }
}
