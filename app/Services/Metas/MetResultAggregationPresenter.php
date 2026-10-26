<?php

namespace App\Services\Metas;

use App\Enums\Metas\DataFieldAggregationType;
use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\DataFieldInputType;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetResult;
use App\Services\MetDataAggregatorService;
use App\Support\Metas\MetIndicatorPrimaryComponentPresenter;
use Illuminate\Support\Collection;

class MetResultAggregationPresenter
{
    private const ZERO_EPSILON = 0.0000001;

    public function __construct(
        private readonly MetIndicatorEvaluationService $indicatorEvaluationService,
        private readonly MetDataAggregatorService $dataAggregatorService,
        private readonly MetIndicatorCalculationDataScopeService $dataScopeService,
    ) {}

    /**
     * @param  Collection<int, MetDataField>  $fields
     */
    public function componentUsesAccumulativeAggregation(Collection $fields): bool
    {
        if ($fields->isEmpty()) {
            return false;
        }

        return $fields->every(function (MetDataField $field): bool {
            if ($field->input_type === DataFieldInputType::LineaBase) {
                return false;
            }

            return $field->aggregation_type?->isAccumulative() ?? false;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $periodos
     * @param  Collection<int, MetDataField>  $effectiveFields
     * @return array<int, array<string, mixed>>
     */
    public function appendCumulativePeriodData(
        array $periodos,
        Collection $effectiveFields,
        ?MetIndicator $indicator = null,
        int $decimales = 2,
        string $decimalesModo = 'redondear',
    ): array {
        $numeradorFields = $effectiveFields->where('componente', DataFieldComponente::Numerador);
        $denominadorFields = $effectiveFields->where('componente', DataFieldComponente::Denominador);
        $isSumatoria = $numeradorFields->isNotEmpty()
            && $this->componentUsesAccumulativeAggregation($numeradorFields);

        if (! $isSumatoria) {
            return $periodos;
        }

        $isDenominadorSumatoria = $denominadorFields->isNotEmpty()
            && $this->componentUsesAccumulativeAggregation($denominadorFields);

        $numAcum = 0.0;
        $denAcum = 0.0;

        foreach ($periodos as &$periodo) {
            if ($periodo['numerador'] === null) {
                $periodo['numerador_acumulado'] = null;
                $periodo['denominador_acumulado'] = null;
                $periodo['resultado_acumulado'] = null;

                continue;
            }

            $numAcum += (float) $periodo['numerador'];
            $denominadorPeriodo = (float) ($periodo['denominador'] ?? 0);

            if ($isDenominadorSumatoria) {
                $denAcum += $denominadorPeriodo;
                $denominadorAcumulado = $denAcum;
            } else {
                $denominadorAcumulado = $denominadorPeriodo;
            }

            $periodo['numerador_acumulado'] = $numAcum;
            $periodo['denominador_acumulado'] = $denominadorAcumulado;

            $evaluated = $this->evaluateAggregatedComponents(
                $numAcum,
                $denominadorAcumulado,
                $decimales,
                $decimalesModo,
                'ANUAL',
                $indicator,
            );

            $periodo['resultado_acumulado'] = $evaluated['resultado'] !== null
                ? round($evaluated['resultado'], 4)
                : null;
        }
        unset($periodo);

        return $periodos;
    }

    /**
     * Acumulados de presentación para fórmula compleja (varios componentes primarios).
     * Re-agrega variables mes a mes (con universo adosados / agrupa_ss) y reevalúa la fórmula.
     *
     * @param  array<int, array<string, mixed>>  $periodos
     * @param  Collection<int, MetDataField>  $effectiveFields
     * @return array<int, array<string, mixed>>
     */
    public function appendComplexCumulativePeriodData(
        array $periodos,
        Collection $effectiveFields,
        MetIndicator $indicator,
        int $centerId,
        int $yearId,
        int $dependencyId,
        int $decimales = 2,
        string $decimalesModo = 'redondear',
        ?MetIndicatorConfiguration $configuration = null,
        ?int $measurementUnitId = null,
    ): array {
        if (! $indicator->relationLoaded('dataFields')) {
            $indicator->load('dataFields');
        }

        if (! $indicator->relationLoaded('formula')) {
            $indicator->load('formula');
        }

        $configuration ??= $indicator->id !== null
            ? MetIndicatorConfiguration::query()
                ->where('met_indicator_id', $indicator->id)
                ->where('cen_center_id', $centerId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $dependencyId)
                ->when(
                    $measurementUnitId !== null,
                    fn ($query) => $query->where('met_measurement_unit_id', $measurementUnitId),
                    fn ($query) => $query->whereNull('met_measurement_unit_id'),
                )
                ->first()
            : null;

        $additionalCenterIds = $configuration !== null
            ? $this->dataScopeService->additionalDataCenterIds(
                $indicator,
                $centerId,
                $configuration,
                $yearId,
                $dependencyId,
                $measurementUnitId,
            )
            : [];

        /** @var array<string, float|null> $variableTotals */
        $variableTotals = [];
        foreach ($indicator->dataFields as $field) {
            $variableTotals[(string) $field->variable_key] = null;
        }

        foreach ($periodos as &$periodo) {
            $periodoKey = (string) ($periodo['periodo'] ?? '');

            if ($periodoKey === '' || $periodoKey === 'ANUAL') {
                $periodo['numerador_acumulado'] = null;
                $periodo['denominador_acumulado'] = null;
                $periodo['resultado_acumulado'] = null;

                continue;
            }

            $periodVariables = $this->dataAggregatorService->aggregateVariables(
                $indicator,
                $centerId,
                $yearId,
                $periodoKey,
                $additionalCenterIds,
                $measurementUnitId,
                $dependencyId,
            );

            $periodHasValues = false;

            foreach ($effectiveFields as $field) {
                $key = (string) $field->variable_key;
                $value = $periodVariables[$key] ?? null;

                if ($value === null) {
                    continue;
                }

                $periodHasValues = true;

                if ($field->input_type === DataFieldInputType::LineaBase) {
                    $variableTotals[$key] = (float) $value;

                    continue;
                }

                if ($field->aggregation_type?->isAccumulative() ?? false) {
                    $variableTotals[$key] = ($variableTotals[$key] ?? 0.0) + (float) $value;

                    continue;
                }

                $variableTotals[$key] = (float) $value;
            }

            if (! $periodHasValues && $periodo['resultado'] === null) {
                $periodo['numerador_acumulado'] = null;
                $periodo['denominador_acumulado'] = null;
                $periodo['resultado_acumulado'] = null;

                continue;
            }

            $primary = $this->dataAggregatorService->resolvePrimaryComponenteValues(
                $indicator,
                $variableTotals,
            );

            $evaluation = $this->indicatorEvaluationService->evaluate(
                $indicator,
                $periodoKey,
                $variableTotals,
                $primary['numerador'],
                $primary['denominador'],
            );

            $periodo['numerador_acumulado'] = $primary['numerador'];
            $periodo['denominador_acumulado'] = $primary['denominador'];
            $periodo['resultado_acumulado'] = $evaluation['ok']
                ? round(
                    $this->applyDecimalMode((float) $evaluation['result'], $decimales, $decimalesModo),
                    4,
                )
                : null;
        }
        unset($periodo);

        return $periodos;
    }

    /**
     * @param  Collection<int, MetResult>  $group
     * @param  Collection<int, MetDataField>  $effectiveFields
     * @param  array<string, int>  $periodoOrder
     * @return array{
     *     numerador: float,
     *     denominador: float,
     *     resultado: float|null,
     *     error_message: string|null,
     *     ultimo_periodo: string|null
     * }
     */
    public function aggregatePeriodResultsToAnnualSummary(
        Collection $group,
        Collection $effectiveFields,
        int $decimales,
        string $decimalesModo,
        array $periodoOrder = [],
        ?MetIndicator $indicator = null,
        ?MetIndicatorConfiguration $configuration = null,
    ): array {
        if ($group->isNotEmpty() && $group->every(
            fn (MetResult $result): bool => $result->resultado === null
                && filled($result->error_message)
                && ! MetResult::storedErrorMessageIsNonCalculationAlert($result->error_message)
        )) {
            $failedResult = $group->first(fn (MetResult $result): bool => filled($result->error_message)
                && ! MetResult::storedErrorMessageIsNonCalculationAlert($result->error_message));

            return [
                'numerador' => null,
                'denominador' => null,
                'resultado' => null,
                'error_message' => $failedResult?->error_message,
                'ultimo_periodo' => $failedResult?->periodo,
            ];
        }

        $anual = $group->firstWhere('periodo', 'ANUAL');

        if ($anual) {
            $lastNonAnual = $group
                ->filter(fn (MetResult $result) => $result->periodo !== 'ANUAL' && $result->resultado !== null)
                ->sortByDesc(fn (MetResult $result) => $periodoOrder[$result->periodo] ?? 0)
                ->first();

            return [
                'numerador' => (float) $anual->numerador,
                'denominador' => (float) $anual->denominador,
                'resultado' => $anual->error_message ? null : (float) $anual->resultado,
                'error_message' => $anual->error_message,
                'ultimo_periodo' => $lastNonAnual?->periodo,
            ];
        }

        $orderedGroup = $group->sortBy(fn (MetResult $result) => $periodoOrder[$result->periodo] ?? 0);
        $lastPeriod = $orderedGroup
            ->filter(fn (MetResult $result) => $result->resultado !== null)
            ->last() ?? $orderedGroup->last();

        $allUltimoDato = $effectiveFields->isNotEmpty()
            && $effectiveFields->every(
                fn (MetDataField $field) => $field->aggregation_type === DataFieldAggregationType::UltimoDato
            );

        if ($allUltimoDato) {
            return [
                'numerador' => (float) $lastPeriod->numerador,
                'denominador' => (float) $lastPeriod->denominador,
                'resultado' => $lastPeriod->error_message ? null : (float) $lastPeriod->resultado,
                'error_message' => $lastPeriod->error_message,
                'ultimo_periodo' => $lastPeriod->periodo,
            ];
        }

        $numeradorFields = $effectiveFields->where('componente', DataFieldComponente::Numerador);
        $denominadorFields = $effectiveFields->where('componente', DataFieldComponente::Denominador);
        $numeradorAccumulative = $this->componentUsesAccumulativeAggregation($numeradorFields);
        $denominadorAccumulative = $this->componentUsesAccumulativeAggregation($denominadorFields);

        $numerador = $numeradorAccumulative
            ? (float) $group->sum(fn (MetResult $result) => (float) $result->numerador)
            : (float) $lastPeriod->numerador;

        $denominador = $denominadorAccumulative
            ? (float) $group->sum(fn (MetResult $result) => (float) $result->denominador)
            : (float) $lastPeriod->denominador;

        if (
            $indicator !== null
            && MetIndicatorPrimaryComponentPresenter::usesComplexPrimaryComponents($indicator->dataFields)
        ) {
            return $this->aggregateComplexFormulaFromStoredPeriods(
                $group,
                $effectiveFields,
                $indicator,
                $decimales,
                $decimalesModo,
                $periodoOrder,
                $lastPeriod,
                $configuration,
            );
        }

        if ($this->hasStoredPrimaryComponentValues($group)) {
            $summary = $this->evaluateAggregatedComponents(
                $numerador,
                $denominador,
                $decimales,
                $decimalesModo,
                'ANUAL',
                $indicator,
            );

            return [
                'numerador' => $numerador,
                'denominador' => $denominador,
                'resultado' => $summary['resultado'],
                'error_message' => $summary['error_message'],
                'ultimo_periodo' => $this->resolveUltimoPeriodo($group, $periodoOrder, $lastPeriod),
            ];
        }

        $factor = $this->resolveScaleFactorFromResults($orderedGroup);

        if (! $this->canDeriveResultFromComponents($numerador, $denominador, $factor)) {
            $storedSummary = $this->resolveSummaryFromStoredPeriodResults($orderedGroup, $lastPeriod);

            if ($storedSummary !== null) {
                return $storedSummary;
            }

            if ($indicator !== null) {
                $variables = $this->indicatorEvaluationService->variablesFromPrimaryComponents(
                    $indicator,
                    $numerador,
                    $denominador,
                );

                $evaluation = $this->indicatorEvaluationService->evaluate(
                    $indicator,
                    'ANUAL',
                    $variables,
                    $numerador,
                    $denominador,
                );

                if (! $evaluation['ok']) {
                    return [
                        'numerador' => $numerador,
                        'denominador' => $denominador,
                        'resultado' => null,
                        'error_message' => $evaluation['message'],
                        'ultimo_periodo' => $lastPeriod?->periodo,
                    ];
                }

                return [
                    'numerador' => $numerador,
                    'denominador' => $denominador,
                    'resultado' => $this->applyDecimalMode((float) $evaluation['result'], $decimales, $decimalesModo),
                    'error_message' => null,
                    'ultimo_periodo' => $group
                        ->filter(fn (MetResult $result) => $result->periodo !== 'ANUAL' && $result->resultado !== null)
                        ->sortByDesc(fn (MetResult $result) => $periodoOrder[$result->periodo] ?? 0)
                        ->first()
                        ?->periodo,
                ];
            }

            if (abs($denominador) < self::ZERO_EPSILON) {
                return [
                    'numerador' => $numerador,
                    'denominador' => $denominador,
                    'resultado' => abs($numerador) < self::ZERO_EPSILON ? 0.0 : null,
                    'error_message' => abs($numerador) < self::ZERO_EPSILON ? null : MetResult::MESSAGE_DIVISOR_CERO,
                    'ultimo_periodo' => $lastPeriod?->periodo,
                ];
            }
        }

        $raw = abs($denominador) < self::ZERO_EPSILON
            ? 0.0
            : ($numerador / $denominador) * ($factor ?? 100);

        return [
            'numerador' => $numerador,
            'denominador' => $denominador,
            'resultado' => $this->applyDecimalMode($raw, $decimales, $decimalesModo),
            'error_message' => null,
            'ultimo_periodo' => $group
                ->filter(fn (MetResult $result) => $result->periodo !== 'ANUAL' && $result->resultado !== null)
                ->sortByDesc(fn (MetResult $result) => $periodoOrder[$result->periodo] ?? 0)
                ->first()
                ?->periodo,
        ];
    }

    /**
     * @param  Collection<int, MetResult>  $group
     * @param  Collection<int, MetDataField>  $effectiveFields
     * @param  array<string, int>  $periodoOrder
     * @return array{
     *     numerador: float|null,
     *     denominador: float|null,
     *     resultado: float|null,
     *     error_message: string|null,
     *     ultimo_periodo: string|null
     * }
     */
    private function aggregateComplexFormulaFromStoredPeriods(
        Collection $group,
        Collection $effectiveFields,
        MetIndicator $indicator,
        int $decimales,
        string $decimalesModo,
        array $periodoOrder,
        ?MetResult $lastPeriod,
        ?MetIndicatorConfiguration $configuration,
    ): array {
        $first = $group->first();

        if ($first === null) {
            return [
                'numerador' => null,
                'denominador' => null,
                'resultado' => null,
                'error_message' => null,
                'ultimo_periodo' => null,
            ];
        }

        $centerId = (int) $first->cen_center_id;
        $yearId = (int) $first->year_id;
        $dependencyId = (int) $first->cen_hie_dependency_id;
        $configuration ??= $this->resolveConfiguration($indicator, $first);
        $measurementUnitId = $first->met_measurement_unit_id !== null
            ? (int) $first->met_measurement_unit_id
            : null;

        $sortedPeriodos = $group
            ->filter(fn (MetResult $result): bool => $result->periodo !== 'ANUAL')
            ->sortBy(fn (MetResult $result) => $periodoOrder[$result->periodo] ?? 0)
            ->pluck('periodo')
            ->unique()
            ->values()
            ->all();

        $variableTotals = $this->aggregateComplexVariableTotalsForPeriodKeys(
            $indicator,
            $effectiveFields,
            $centerId,
            $yearId,
            $dependencyId,
            $sortedPeriodos,
            $configuration,
            $measurementUnitId,
        );

        $primary = $this->dataAggregatorService->resolvePrimaryComponenteValues($indicator, $variableTotals);

        $evaluation = $this->indicatorEvaluationService->evaluate(
            $indicator,
            'ANUAL',
            $variableTotals,
            $primary['numerador'],
            $primary['denominador'],
        );

        return [
            'numerador' => $primary['numerador'],
            'denominador' => $primary['denominador'],
            'resultado' => $evaluation['ok']
                ? $this->applyDecimalMode((float) $evaluation['result'], $decimales, $decimalesModo)
                : null,
            'error_message' => $evaluation['ok'] ? null : $evaluation['message'],
            'ultimo_periodo' => $this->resolveUltimoPeriodo($group, $periodoOrder, $lastPeriod),
        ];
    }

    /**
     * Totales de variables para fórmula compleja re-agregando cada período subanual
     * (mismo universo adosados / agrupa_ss que el motor).
     *
     * @param  array<int, string>  $periodKeys
     * @param  Collection<int, MetDataField>  $effectiveFields
     * @return array<string, float|null>
     */
    public function aggregateComplexVariableTotalsForPeriodKeys(
        MetIndicator $indicator,
        Collection $effectiveFields,
        int $centerId,
        int $yearId,
        int $dependencyId,
        array $periodKeys,
        ?MetIndicatorConfiguration $configuration = null,
        ?int $measurementUnitId = null,
    ): array {
        if (! $indicator->relationLoaded('dataFields')) {
            $indicator->load('dataFields');
        }

        $configuration ??= $indicator->id !== null
            ? MetIndicatorConfiguration::query()
                ->where('met_indicator_id', $indicator->id)
                ->where('cen_center_id', $centerId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $dependencyId)
                ->when(
                    $measurementUnitId !== null,
                    fn ($query) => $query->where('met_measurement_unit_id', $measurementUnitId),
                    fn ($query) => $query->whereNull('met_measurement_unit_id'),
                )
                ->first()
            : null;

        $additionalCenterIds = $configuration !== null
            ? $this->dataScopeService->additionalDataCenterIds(
                $indicator,
                $centerId,
                $configuration,
                $yearId,
                $dependencyId,
                $measurementUnitId,
            )
            : [];

        /** @var array<string, float|null> $variableTotals */
        $variableTotals = [];

        foreach ($indicator->dataFields as $field) {
            $variableTotals[(string) $field->variable_key] = null;
        }

        foreach ($periodKeys as $periodo) {
            $periodVariables = $this->dataAggregatorService->aggregateVariables(
                $indicator,
                $centerId,
                $yearId,
                (string) $periodo,
                $additionalCenterIds,
                $measurementUnitId,
                $dependencyId,
            );

            foreach ($effectiveFields as $field) {
                $key = (string) $field->variable_key;
                $value = $periodVariables[$key] ?? null;

                if ($value === null) {
                    continue;
                }

                if ($field->input_type === DataFieldInputType::LineaBase) {
                    $variableTotals[$key] = (float) $value;

                    continue;
                }

                if ($field->aggregation_type?->isAccumulative() ?? false) {
                    $variableTotals[$key] = ($variableTotals[$key] ?? 0.0) + (float) $value;

                    continue;
                }

                $variableTotals[$key] = (float) $value;
            }
        }

        return $variableTotals;
    }

    private function resolveConfiguration(
        MetIndicator $indicator,
        MetResult $result,
    ): ?MetIndicatorConfiguration {
        if (
            $result->cen_center_id === null
            || $result->year_id === null
            || $result->cen_hie_dependency_id === null
        ) {
            return null;
        }

        return MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $indicator->id)
            ->where('cen_center_id', $result->cen_center_id)
            ->where('year_id', $result->year_id)
            ->where('cen_hie_dependency_id', $result->cen_hie_dependency_id)
            ->when(
                $result->met_measurement_unit_id !== null,
                fn ($query) => $query->where('met_measurement_unit_id', $result->met_measurement_unit_id),
                fn ($query) => $query->whereNull('met_measurement_unit_id'),
            )
            ->first();
    }

    /**
     * @param  Collection<int, MetResult>  $group
     */
    private function hasStoredPrimaryComponentValues(Collection $group): bool
    {
        return $group->contains(
            fn (MetResult $result): bool => $result->periodo !== 'ANUAL'
                && ($result->numerador !== null || $result->denominador !== null)
        );
    }

    /**
     * @return array{resultado: float|null, error_message: string|null}
     */
    private function evaluateAggregatedComponents(
        float $numerador,
        float $denominador,
        int $decimales,
        string $decimalesModo,
        string $periodo,
        ?MetIndicator $indicator,
    ): array {
        if ($indicator !== null && $indicator->formula && ! empty($indicator->formula->expression)) {
            $variables = $this->indicatorEvaluationService->variablesFromPrimaryComponents(
                $indicator,
                $numerador,
                $denominador,
            );

            $evaluation = $this->indicatorEvaluationService->evaluate(
                $indicator,
                $periodo,
                $variables,
                $numerador,
                $denominador,
            );

            if (! $evaluation['ok']) {
                return [
                    'resultado' => null,
                    'error_message' => $evaluation['message'],
                ];
            }

            return [
                'resultado' => $this->applyDecimalMode((float) $evaluation['result'], $decimales, $decimalesModo),
                'error_message' => null,
            ];
        }

        if (abs($denominador) < self::ZERO_EPSILON) {
            return [
                'resultado' => abs($numerador) < self::ZERO_EPSILON ? 0.0 : null,
                'error_message' => abs($numerador) < self::ZERO_EPSILON ? null : MetResult::MESSAGE_DIVISOR_CERO,
            ];
        }

        return [
            'resultado' => $this->applyDecimalMode(($numerador / $denominador) * 100, $decimales, $decimalesModo),
            'error_message' => null,
        ];
    }

    /**
     * @param  Collection<int, MetResult>  $group
     * @param  array<string, int>  $periodoOrder
     */
    private function resolveUltimoPeriodo(Collection $group, array $periodoOrder, ?MetResult $lastPeriod): ?string
    {
        return $group
            ->filter(fn (MetResult $result) => $result->periodo !== 'ANUAL' && $result->resultado !== null)
            ->sortByDesc(fn (MetResult $result) => $periodoOrder[$result->periodo] ?? 0)
            ->first()
            ?->periodo
            ?? $lastPeriod?->periodo;
    }

    private function canDeriveResultFromComponents(float $numerador, float $denominador, ?float $factor): bool
    {
        if ($factor === null) {
            return false;
        }

        if (abs($denominador) < self::ZERO_EPSILON) {
            return abs($numerador) < self::ZERO_EPSILON;
        }

        return true;
    }

    /**
     * @param  Collection<int, MetResult>  $orderedGroup
     * @return array{
     *     numerador: float,
     *     denominador: float,
     *     resultado: float,
     *     error_message: null,
     *     ultimo_periodo: string
     * }|null
     */
    private function resolveSummaryFromStoredPeriodResults(Collection $orderedGroup, ?MetResult $lastPeriod): ?array
    {
        $storedPeriod = $orderedGroup
            ->filter(fn (MetResult $result) => $result->periodo !== 'ANUAL'
                && $result->resultado !== null
                && $result->error_message === null)
            ->last();

        if ($storedPeriod === null) {
            return null;
        }

        $summaryPeriod = $lastPeriod !== null
            && $lastPeriod->resultado !== null
            && $lastPeriod->error_message === null
            ? $lastPeriod
            : $storedPeriod;

        return [
            'numerador' => (float) ($summaryPeriod->numerador ?? 0),
            'denominador' => (float) ($summaryPeriod->denominador ?? 0),
            'resultado' => (float) $summaryPeriod->resultado,
            'error_message' => null,
            'ultimo_periodo' => $summaryPeriod->periodo,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $periodos
     */
    private function resolveScaleFactorFromPeriodRows(array $periodos): ?float
    {
        foreach ($periodos as $periodo) {
            if (
                $periodo['numerador'] !== null
                && $periodo['denominador'] !== null
                && (float) $periodo['denominador'] > 0
                && $periodo['resultado'] !== null
                && abs((float) $periodo['numerador']) >= self::ZERO_EPSILON
            ) {
                return (float) $periodo['resultado'] / ((float) $periodo['numerador'] / (float) $periodo['denominador']);
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, MetResult>  $results
     */
    private function resolveScaleFactorFromResults(Collection $results): ?float
    {
        foreach ($results as $result) {
            if (
                $result->numerador !== null
                && $result->denominador !== null
                && (float) $result->denominador > 0
                && $result->resultado !== null
                && $result->error_message === null
                && abs((float) $result->numerador) >= self::ZERO_EPSILON
            ) {
                return (float) $result->resultado / ((float) $result->numerador / (float) $result->denominador);
            }
        }

        return null;
    }

    private function applyDecimalMode(float $raw, int $decimales, string $decimalesModo): float
    {
        $factor = 10 ** max(0, min(10, $decimales));

        if ($decimalesModo === 'truncar') {
            return $raw >= 0
                ? floor($raw * $factor) / $factor
                : ceil($raw * $factor) / $factor;
        }

        return round($raw, $decimales);
    }
}
