<?php

namespace App\Services\Metas;

use App\Enums\Metas\MetInstrumentPeriodo;
use App\Models\CenCenter;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetResult;
use App\Models\User;
use App\Services\MetDataAggregatorService;
use App\Support\Metas\MetIndicatorMetaPresenter;
use App\Support\Metas\MetIndicatorPrimaryComponentPresenter;

class MetComplexFormulaVariablesModalService
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

    public function __construct(
        private readonly MetDataAggregatorService $dataAggregatorService,
        private readonly MetIndicatorCalculationDataScopeService $dataScopeService,
        private readonly MetFieldSsConfigService $fieldSsConfigService,
        private readonly MetResultAggregationPresenter $resultAggregationPresenter,
        private readonly MetIndicatorEvaluationService $indicatorEvaluationService,
    ) {}

    /**
     * @return array{
     *     indicator_clave: string,
     *     indicator_nombre: string,
     *     center_nombre: string,
     *     center_codigo: string,
     *     periodo: string|null,
     *     periodo_label: string,
     *     periodo_note: string|null,
     *     expression: string|null,
     *     variables: list<array{variable_key: string, label: string, value: float|null, value_display: string}>,
     *     resultado: float|null,
     *     resultado_display: string|null,
     *     unidad_medida: string|null,
     *     decimales: int,
     *     empty_message: string|null
     * }
     */
    public function payload(
        User $user,
        int $indicatorTypeId,
        int $indicatorId,
        int $centerId,
        int $yearId,
        ?string $periodo = null,
        bool $periodoIsReferenceFromSummary = false,
    ): array {
        $dependencyId = (int) $user->cen_hie_dependency_id;
        abort_unless($dependencyId > 0, 403);

        $indicatorType = IndicatorType::query()->findOrFail($indicatorTypeId);

        $indicator = MetIndicator::query()
            ->where('id', $indicatorId)
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('year_id', $yearId)
            ->with(['dataFields', 'formula'])
            ->first();

        abort_unless($indicator !== null, 404);

        $center = CenCenter::query()->findOrFail($centerId);
        $usesUnits = $indicatorType->usesInternalUnitMeasurement();

        $configQuery = MetIndicatorConfiguration::query()
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->where('cen_center_id', $centerId)
            ->where('met_indicator_id', $indicatorId)
            ->where('aplica', true);

        $config = $usesUnits
            ? $configQuery->clone()->whereNotNull('met_measurement_unit_id')->first()
            : $configQuery->clone()->whereNull('met_measurement_unit_id')->first();

        abort_unless($config !== null, 403);

        $emptyBase = [
            'indicator_clave' => (string) $indicator->clave,
            'indicator_nombre' => (string) $indicator->nombre,
            'center_nombre' => (string) ($center->nombre_corto ?: $center->nombre_oficial ?: ''),
            'center_codigo' => (string) ($center->codigo ?? ''),
            'periodo' => null,
            'periodo_label' => '—',
            'periodo_note' => null,
            'expression' => $indicator->formula?->description,
            'variables' => [],
            'resultado' => null,
            'resultado_display' => null,
            'unidad_medida' => $indicator->unidad_medida,
            'decimales' => (int) ($indicator->decimales ?? 2),
            'empty_message' => null,
        ];

        if ($indicator->dataFields->isEmpty()) {
            $emptyBase['empty_message'] = 'Este indicador no tiene campos de maqueta configurados.';

            return $emptyBase;
        }

        if (! MetIndicatorPrimaryComponentPresenter::usesComplexPrimaryComponents($indicator->dataFields)) {
            $emptyBase['empty_message'] = 'Este indicador no usa fórmula compleja.';

            return $emptyBase;
        }

        $resolvedPeriodo = $periodo;
        $referencePeriodo = null;

        if ($resolvedPeriodo === null || $resolvedPeriodo === '') {
            $resolvedPeriodo = $this->resolveReferencePeriodo($indicator, $centerId, $dependencyId, $yearId);
            $periodoIsReferenceFromSummary = true;
        }

        if ($resolvedPeriodo === null || $resolvedPeriodo === '') {
            $emptyBase['empty_message'] = 'No hay período con datos para mostrar las variables.';

            return $emptyBase;
        }

        if ($periodoIsReferenceFromSummary && $resolvedPeriodo !== 'ANUAL') {
            $referencePeriodo = $resolvedPeriodo;
        }

        $useAnnualAggregation = $this->shouldUseAnnualVariableAggregation(
            $resolvedPeriodo,
            $periodoIsReferenceFromSummary,
        );

        $measurementUnitId = $usesUnits ? $config->met_measurement_unit_id : null;

        $effectiveFields = $this->fieldSsConfigService->applyEffectiveTypes(
            $indicator->dataFields,
            $dependencyId,
            $yearId,
        )->sortBy('orden')->values();

        if ($useAnnualAggregation) {
            $aggregated = $this->resultAggregationPresenter->aggregateComplexVariableTotalsForPeriodKeys(
                $indicator,
                $effectiveFields,
                $centerId,
                $yearId,
                $dependencyId,
                $this->resolveAnnualPeriodKeys($indicator, $centerId, $dependencyId, $yearId),
                $config,
                $measurementUnitId !== null ? (int) $measurementUnitId : null,
            );
            $displayPeriodo = 'ANUAL';
            $periodoNote = $referencePeriodo !== null
                ? 'Valores acumulados del resumen anual. Período de referencia: '.$this->periodoLabel($referencePeriodo).'.'
                : 'Valores acumulados del resumen anual (mismo universo de cálculo que la fila).';
            $resultado = $this->resolveAnnualResultado(
                $indicator,
                $indicatorId,
                $centerId,
                $dependencyId,
                $yearId,
                $aggregated,
            );
        } else {
            $additionalCenterIds = $this->dataScopeService->additionalDataCenterIds(
                $indicator,
                $centerId,
                $config,
                $yearId,
                $dependencyId,
                $measurementUnitId !== null ? (int) $measurementUnitId : null,
            );

            $aggregated = $this->dataAggregatorService->aggregateVariables(
                $indicator,
                $centerId,
                $yearId,
                $resolvedPeriodo,
                $additionalCenterIds,
                $measurementUnitId !== null ? (int) $measurementUnitId : null,
                $dependencyId,
            );
            $displayPeriodo = $resolvedPeriodo;
            $periodoNote = null;
            $resultado = $this->resolveResultado(
                $indicatorId,
                $centerId,
                $dependencyId,
                $yearId,
                $resolvedPeriodo,
            );
        }

        $variables = [];
        foreach ($effectiveFields as $field) {
            $key = (string) $field->variable_key;
            $value = array_key_exists($key, $aggregated) ? $aggregated[$key] : null;
            $variables[] = [
                'variable_key' => $key,
                'label' => (string) $field->label,
                'value' => $value !== null ? (float) $value : null,
                'value_display' => $value !== null
                    ? MetIndicatorMetaPresenter::formatCompactNumber((float) $value)
                    : '—',
            ];
        }

        $decimales = (int) ($indicator->decimales ?? 2);
        $hasAnyValue = collect($variables)->contains(fn (array $row): bool => $row['value'] !== null);

        return [
            'indicator_clave' => (string) $indicator->clave,
            'indicator_nombre' => (string) $indicator->nombre,
            'center_nombre' => (string) ($center->nombre_corto ?: $center->nombre_oficial ?: ''),
            'center_codigo' => (string) ($center->codigo ?? ''),
            'periodo' => $displayPeriodo,
            'periodo_label' => $this->periodoLabel($displayPeriodo),
            'periodo_note' => $periodoNote,
            'expression' => $indicator->formula?->description,
            'variables' => $variables,
            'resultado' => $resultado,
            'resultado_display' => $resultado !== null
                ? number_format($resultado, $decimales)
                : null,
            'unidad_medida' => $indicator->unidad_medida,
            'decimales' => $decimales,
            'empty_message' => $hasAnyValue
                ? null
                : 'Sin datos de carga para las variables en este período.',
        ];
    }

    private function shouldUseAnnualVariableAggregation(string $periodo, bool $periodoIsReferenceFromSummary): bool
    {
        return $periodo === 'ANUAL' || $periodoIsReferenceFromSummary;
    }

    /**
     * @return array<int, string>
     */
    private function resolveAnnualPeriodKeys(
        MetIndicator $indicator,
        int $centerId,
        int $dependencyId,
        int $yearId,
    ): array {
        $storedPeriods = MetResult::query()
            ->where('met_indicator_id', $indicator->id)
            ->where('cen_center_id', $centerId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->where('year_id', $yearId)
            ->where('periodo', '!=', 'ANUAL')
            ->pluck('periodo')
            ->unique()
            ->sortBy(fn (string $periodo): int => self::PERIODO_ORDER[$periodo] ?? 0)
            ->values()
            ->all();

        if ($storedPeriods !== []) {
            return $storedPeriods;
        }

        return $this->dataAggregatorService->resolvePeriods($indicator, $dependencyId, $yearId);
    }

    /**
     * @param  array<string, float|null>  $variableTotals
     */
    private function resolveAnnualResultado(
        MetIndicator $indicator,
        int $indicatorId,
        int $centerId,
        int $dependencyId,
        int $yearId,
        array $variableTotals,
    ): ?float {
        $storedAnnual = MetResult::query()
            ->where('met_indicator_id', $indicatorId)
            ->where('cen_center_id', $centerId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->where('year_id', $yearId)
            ->where('periodo', 'ANUAL')
            ->first();

        if ($storedAnnual !== null && $storedAnnual->resultado !== null && ! filled($storedAnnual->error_message)) {
            return (float) $storedAnnual->resultado;
        }

        $primary = $this->dataAggregatorService->resolvePrimaryComponenteValues($indicator, $variableTotals);
        $evaluation = $this->indicatorEvaluationService->evaluate(
            $indicator,
            'ANUAL',
            $variableTotals,
            $primary['numerador'],
            $primary['denominador'],
        );

        if (! $evaluation['ok']) {
            return null;
        }

        return (float) $evaluation['result'];
    }

    private function resolveReferencePeriodo(
        MetIndicator $indicator,
        int $centerId,
        int $dependencyId,
        int $yearId,
    ): ?string {
        $result = MetResult::query()
            ->where('met_indicator_id', $indicator->id)
            ->where('cen_center_id', $centerId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->where('year_id', $yearId)
            ->where('periodo', '!=', 'ANUAL')
            ->whereNotNull('resultado')
            ->orderByDesc('id')
            ->first();

        if ($result !== null) {
            return (string) $result->periodo;
        }

        $any = MetResult::query()
            ->where('met_indicator_id', $indicator->id)
            ->where('cen_center_id', $centerId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->where('year_id', $yearId)
            ->where('periodo', '!=', 'ANUAL')
            ->orderByDesc('id')
            ->first();

        return $any !== null ? (string) $any->periodo : null;
    }

    private function resolveResultado(
        int $indicatorId,
        int $centerId,
        int $dependencyId,
        int $yearId,
        string $periodo,
    ): ?float {
        $result = MetResult::query()
            ->where('met_indicator_id', $indicatorId)
            ->where('cen_center_id', $centerId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->where('year_id', $yearId)
            ->where('periodo', $periodo)
            ->first();

        if ($result === null || $result->resultado === null || filled($result->error_message)) {
            return null;
        }

        return (float) $result->resultado;
    }

    private function periodoLabel(string $periodo): string
    {
        $labels = MetInstrumentPeriodo::cortaPorClave();

        return $labels[$periodo] ?? $periodo;
    }
}
