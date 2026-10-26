<?php

namespace App\Services\Metas;

use App\Enums\Metas\MetDataLoadStatus;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetResult;
use App\Services\MetCalculationService;
use App\Support\Metas\MetIndicatorPrimaryComponentPresenter;
use Illuminate\Support\Collection;

class MetIndicatorDesempenoByEstablishmentService
{
    /**
     * Memo request-scoped para evitar doble buildBatch con el mismo fingerprint (SDD-169).
     *
     * @var array<int, array{
     *     indicador_nombre: string,
     *     umbral_superior: float|null,
     *     umbral_inferior: float|null,
     *     uses_complex_primary_components: bool,
     *     rows: list<array<string, mixed>>,
     *     promedio_cumplimiento: float|null
     * }>|null
     */
    private ?array $batchMemoPayloads = null;

    private ?string $batchMemoKey = null;

    /**
     * @var array<string, int>
     */
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

    /**
     * @return array{
     *     indicador_nombre: string,
     *     umbral_superior: float|null,
     *     umbral_inferior: float|null,
     *     uses_complex_primary_components: bool,
     *     rows: list<array<string, mixed>>,
     *     promedio_cumplimiento: float|null
     * }
     */
    public function buildForIndicator(MetIndicator $indicator, int $dependencyId, int $yearId): array
    {
        $indicator->loadMissing(['dataFields', 'indicatorType']);

        $configs = $this->loadConfigurationsForIndicator($indicator, $dependencyId, $yearId);
        $rawResults = $this->loadResultsForIndicator($indicator, $dependencyId, $yearId);

        return $this->buildPayloadForIndicator($indicator, $dependencyId, $yearId, $configs, $rawResults);
    }

    /**
     * @param  Collection<int, MetIndicator>  $indicators
     * @return array<int, array{
     *     indicador_nombre: string,
     *     umbral_superior: float|null,
     *     umbral_inferior: float|null,
     *     uses_complex_primary_components: bool,
     *     rows: list<array<string, mixed>>,
     *     promedio_cumplimiento: float|null
     * }>
     */
    public function buildBatchForIndicators(Collection $indicators, int $dependencyId, int $yearId): array
    {
        if ($indicators->isEmpty()) {
            return [];
        }

        $memoKey = $this->batchMemoFingerprint($indicators, $dependencyId, $yearId);

        if ($this->batchMemoKey === $memoKey && $this->batchMemoPayloads !== null) {
            return $this->batchMemoPayloads;
        }

        $indicators->loadMissing(['dataFields', 'indicatorType', 'formula']);

        $indicatorIds = $indicators->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $allConfigs = MetIndicatorConfiguration::query()
            ->whereIn('met_indicator_id', $indicatorIds)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->with('center:id,nombre_corto,codigo')
            ->get()
            ->groupBy('met_indicator_id');

        $allResults = MetResult::query()
            ->whereIn('met_indicator_id', $indicatorIds)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->where('year_id', $yearId)
            ->with('center:id,nombre_corto,codigo')
            ->get()
            ->groupBy('met_indicator_id');

        $dataScopeService = app(MetIndicatorCalculationDataScopeService::class);
        $lastLoadedMonthService = app(MetIndicatorLastLoadedMonthService::class);

        $indicatorTypeIds = $indicators
            ->map(fn (MetIndicator $indicator): int => (int) $indicator->indicator_type_id)
            ->unique()
            ->values()
            ->all();

        foreach ($indicatorTypeIds as $indicatorTypeId) {
            $dataScopeService->preloadAdosadosForInstrument($indicatorTypeId, $yearId, $dependencyId);
        }

        $dataScopeService->preloadAplicaCentersForIndicators($indicatorIds, $yearId, $dependencyId, null);

        $normalizedByIndicator = [];
        $allScopeCenterIds = [];
        $excludeUnits = true;

        foreach ($indicators as $indicator) {
            $configs = $this->normalizeConfigurations(
                $indicator,
                $allConfigs->get($indicator->id, collect()),
            );
            $normalizedByIndicator[$indicator->id] = $configs;

            if ($indicator->indicatorType?->usesInternalUnitMeasurement()) {
                $excludeUnits = false;
            }

            foreach ($configs as $centerId => $config) {
                if (! $config instanceof MetIndicatorConfiguration) {
                    continue;
                }

                $measurementUnitId = $config->met_measurement_unit_id !== null
                    ? (int) $config->met_measurement_unit_id
                    : null;

                foreach (
                    $dataScopeService->allDataCenterIds(
                        $indicator,
                        (int) $centerId,
                        $config,
                        $yearId,
                        $dependencyId,
                        $measurementUnitId,
                    ) as $scopeId
                ) {
                    $allScopeCenterIds[] = (int) $scopeId;
                }
            }
        }

        $lastLoadedMonthService->preloadMonthlyPool(
            $indicatorIds,
            array_values(array_unique($allScopeCenterIds)),
            $dependencyId,
            $yearId,
            $excludeUnits,
        );

        $incompleteByIndicator = app(MetIndicatorIncompletePeriodService::class)
            ->incompletePeriodsByIndicators(
                $indicators,
                $dependencyId,
                $yearId,
                array_values(array_unique($allScopeCenterIds)),
                $excludeUnits,
            );

        $configCenterIds = [];
        foreach ($normalizedByIndicator as $configs) {
            foreach ($configs->keys() as $centerId) {
                $configCenterIds[] = (int) $centerId;
            }
        }

        $persistedErrorsByIndicator = app(MetIndicatorCalculationErrorService::class)
            ->persistedErrorsByIndicators(
                $indicatorIds,
                $dependencyId,
                $yearId,
                array_values(array_unique($configCenterIds)),
            );

        $payloads = [];

        foreach ($indicators as $indicator) {
            $configs = $normalizedByIndicator[$indicator->id] ?? collect();
            $rawResults = $this->normalizeResults(
                $indicator,
                $allResults->get($indicator->id, collect()),
            );

            $payloads[$indicator->id] = $this->buildPayloadForIndicator(
                $indicator,
                $dependencyId,
                $yearId,
                $configs,
                $rawResults,
                $incompleteByIndicator->get((int) $indicator->id, collect()),
                $persistedErrorsByIndicator->get((int) $indicator->id, collect()),
            );
        }

        $this->batchMemoKey = $memoKey;
        $this->batchMemoPayloads = $payloads;

        return $payloads;
    }

    /**
     * @param  Collection<int, MetIndicator>  $indicators
     */
    private function batchMemoFingerprint(Collection $indicators, int $dependencyId, int $yearId): string
    {
        $ids = $indicators->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();

        return $dependencyId.'|'.$yearId.'|'.implode(',', $ids);
    }

    /**
     * @return Collection<int|string, MetIndicatorConfiguration>
     */
    private function loadConfigurationsForIndicator(
        MetIndicator $indicator,
        int $dependencyId,
        int $yearId,
    ): Collection {
        $configsQuery = MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $indicator->id)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->with('center:id,nombre_corto,codigo');

        if (! $indicator->indicatorType?->usesInternalUnitMeasurement()) {
            $configsQuery->whereNull('met_measurement_unit_id')
                ->where('aplica', true);
        }

        return $this->normalizeConfigurations($indicator, $configsQuery->get());
    }

    /**
     * @param  Collection<int, MetIndicatorConfiguration>  $configs
     * @return Collection<int|string, MetIndicatorConfiguration>
     */
    private function normalizeConfigurations(MetIndicator $indicator, Collection $configs): Collection
    {
        if ($indicator->indicatorType?->usesInternalUnitMeasurement()) {
            return $configs
                ->groupBy('cen_center_id')
                ->filter(fn (Collection $rows): bool => $rows->contains(
                    fn (MetIndicatorConfiguration $config): bool => (bool) ($config->aplica ?? true),
                ))
                ->map(function (Collection $rows) {
                    $metaVals = $rows->pluck('meta_efectiva')->filter(fn ($v) => $v !== null);

                    $merged = $rows->first()->replicate();
                    $merged->meta_efectiva = $metaVals->isNotEmpty() ? (float) $metaVals->avg() : null;
                    $merged->seleccionado = $rows->contains(fn ($x) => (bool) $x->seleccionado);

                    return $merged;
                });
        }

        return $configs
            ->when(
                ! $indicator->indicatorType?->usesInternalUnitMeasurement(),
                fn (Collection $collection) => $collection
                    ->whereNull('met_measurement_unit_id')
                    ->where('aplica', true),
            )
            ->keyBy('cen_center_id');
    }

    /**
     * @return Collection<int, MetResult>
     */
    private function loadResultsForIndicator(
        MetIndicator $indicator,
        int $dependencyId,
        int $yearId,
    ): Collection {
        $rawResults = MetResult::query()
            ->where('met_indicator_id', $indicator->id)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->where('year_id', $yearId)
            ->with('center:id,nombre_corto,codigo')
            ->get();

        return $this->normalizeResults($indicator, $rawResults);
    }

    /**
     * @param  Collection<int, MetResult>  $rawResults
     * @return Collection<int, MetResult>
     */
    private function normalizeResults(MetIndicator $indicator, Collection $rawResults): Collection
    {
        if ($indicator->indicatorType?->usesInternalUnitMeasurement()) {
            return $rawResults
                ->groupBy(fn (MetResult $r) => $r->cen_center_id.'-'.$r->periodo)
                ->map(function ($rows) {
                    $first = $rows->firstOrFail();
                    $valid = $rows->filter(fn (MetResult $r) => $r->error_message === null && $r->resultado !== null);
                    $synthetic = $first->replicate();
                    if ($valid->isEmpty()) {
                        return $rows->sortByDesc('id')->first();
                    }
                    $synthetic->numerador = (float) $rows->avg('numerador');
                    $synthetic->denominador = (float) $rows->avg('denominador');
                    $synthetic->resultado = (float) $valid->avg('resultado');
                    $synthetic->error_message = null;
                    $synthetic->met_measurement_unit_id = null;

                    return $synthetic;
                })
                ->values();
        }

        return $rawResults;
    }

    /**
     * @param  Collection<int|string, MetIndicatorConfiguration>  $configs
     * @param  Collection<int, MetResult>  $rawResults
     * @param  Collection<int, array{count: int, periodos: array<int, string>, message: string}>|null  $preloadedIncompletePeriodsByCenter
     * @param  Collection<int, array{count: int, periodos: array<int, string>, message: string}>|null  $preloadedPersistedErrorsByCenter
     * @return array{
     *     indicador_nombre: string,
     *     umbral_superior: float|null,
     *     umbral_inferior: float|null,
     *     uses_complex_primary_components: bool,
     *     rows: list<array<string, mixed>>,
     *     promedio_cumplimiento: float|null
     * }
     */
    private function buildPayloadForIndicator(
        MetIndicator $indicator,
        int $dependencyId,
        int $yearId,
        Collection $configs,
        Collection $rawResults,
        ?Collection $preloadedIncompletePeriodsByCenter = null,
        ?Collection $preloadedPersistedErrorsByCenter = null,
    ): array {
        $usesComplexPrimaryComponents = MetIndicatorPrimaryComponentPresenter::usesComplexPrimaryComponents(
            $indicator->dataFields,
        );

        $metaNacional = (float) ($indicator->meta_valor ?? 0);
        $metaOp = $indicator->meta_operador?->value ?? '>=';
        $decimales = $indicator->decimales ?? 2;
        $decimalesModo = $indicator->decimales_modo?->value ?? 'redondear';

        $effectiveFields = app(MetFieldSsConfigService::class)->applyEffectiveTypes(
            $indicator->dataFields,
            $dependencyId,
            $yearId,
        );

        $aggregationPresenter = app(MetResultAggregationPresenter::class);
        $allResults = $rawResults->groupBy('cen_center_id');

        $centerIds = $configs->keys()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $excludeMeasurementUnitRows = ! $indicator->indicatorType?->usesInternalUnitMeasurement();
        $calculationErrorService = app(MetIndicatorCalculationErrorService::class);
        $incompletePeriodsByCenter = $preloadedIncompletePeriodsByCenter
            ?? app(MetIndicatorIncompletePeriodService::class)->incompletePeriodsByCenterId(
                (int) $indicator->id,
                $dependencyId,
                $yearId,
                $centerIds,
            );

        $persistedErrorsByCenter = $preloadedPersistedErrorsByCenter
            ?? $calculationErrorService->persistedErrorsByCenterId(
                $indicator->id,
                $dependencyId,
                $yearId,
                $centerIds,
            );

        $loadSnapshots = app(MetIndicatorLastLoadedMonthService::class)->snapshotsByIndicatorAndCenterIds(
            $indicator,
            $centerIds,
            $dependencyId,
            $yearId,
            $excludeMeasurementUnitRows,
            $persistedErrorsByCenter,
            $configs,
        );

        $rows = [];
        foreach ($configs as $centerId => $config) {
            $center = $config->center;
            $group = $allResults->get((int) $centerId, collect());
            $metaEfectiva = $config->meta_efectiva !== null ? (float) $config->meta_efectiva : $metaNacional;

            if ($group->isEmpty()) {
                $aggregated = [
                    'numerador' => null,
                    'denominador' => null,
                    'resultado' => null,
                    'error_message' => null,
                    'ultimo_periodo' => null,
                ];
            } else {
                $aggregated = $aggregationPresenter->aggregatePeriodResultsToAnnualSummary(
                    $group,
                    $effectiveFields,
                    $decimales,
                    $decimalesModo,
                    self::PERIODO_ORDER,
                    $indicator,
                    $config,
                );
            }

            $numerador = $aggregated['numerador'];
            $denominador = $aggregated['denominador'];
            $resultado = $aggregated['resultado'];
            $errorMessage = $aggregated['error_message'];
            $ultimoPeriodo = $aggregated['ultimo_periodo'];

            $loadSnapshot = $loadSnapshots->get((int) $centerId, [
                'ultimo_mes_cargado' => null,
                'estado_carga' => MetDataLoadStatus::SinDatos,
            ]);

            if ($this->storedMessageIsCalculationError($errorMessage)) {
                $loadSnapshot['estado_carga'] = MetDataLoadStatus::ErrorCalculo;
            }

            $loadStatus = $loadSnapshot['estado_carga'] instanceof MetDataLoadStatus
                ? $loadSnapshot['estado_carga']
                : MetDataLoadStatus::tryFrom((string) $loadSnapshot['estado_carga']) ?? MetDataLoadStatus::SinDatos;

            $estadoCarga = $loadStatus->value;

            $cumplimiento = $this->resolveCumplimiento(
                $indicator,
                $resultado,
                $metaEfectiva,
                $loadStatus,
                $effectiveFields->isNotEmpty(),
            );

            $incompleteEntry = $incompletePeriodsByCenter->get((int) $centerId);

            $rows[] = [
                'cen_center_id' => (int) $centerId,
                'centro_nombre' => $center?->nombre_corto ?? '—',
                'centro_codigo' => $center?->codigo ?? '',
                'agrupa_ss' => (bool) ($config->agrupa_ss ?? false),
                'convenido' => (bool) ($config->seleccionado ?? false),
                'meta_nacional' => $metaNacional,
                'meta_efectiva' => $metaEfectiva,
                'meta_op' => $metaOp,
                'decimales' => $decimales,
                'unidad_medida' => $indicator->unidad_medida,
                'ultimo_periodo' => $ultimoPeriodo,
                'ultimo_mes_cargado' => $loadSnapshot['ultimo_mes_cargado'],
                'estado_carga' => $estadoCarga,
                'numerador' => $numerador,
                'denominador' => $denominador,
                'resultado' => $resultado,
                'error' => $errorMessage,
                'cumplimiento' => $cumplimiento,
                'incomplete_period_count' => $incompleteEntry['count'] ?? 0,
                'incomplete_period_labels' => $incompleteEntry['periodos'] ?? [],
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['centro_nombre'], $b['centro_nombre']));

        $cumplimientos = array_values(array_filter(
            array_column($rows, 'cumplimiento'),
            fn ($value): bool => $value !== null,
        ));

        $promedioCumplimiento = $cumplimientos !== []
            ? round(array_sum($cumplimientos) / count($cumplimientos), 1)
            : null;

        return [
            'indicador_nombre' => '['.$indicator->clave.'] '.$indicator->nombre,
            'umbral_superior' => $indicator->umbral_superior !== null
                ? (float) $indicator->umbral_superior
                : null,
            'umbral_inferior' => $indicator->umbral_inferior !== null
                ? (float) $indicator->umbral_inferior
                : null,
            'uses_complex_primary_components' => $usesComplexPrimaryComponents,
            'rows' => $rows,
            'promedio_cumplimiento' => $promedioCumplimiento,
        ];
    }

    private function storedMessageIsCalculationError(?string $message): bool
    {
        return filled($message)
            && ! MetResult::storedErrorMessageIsNonCalculationAlert($message);
    }

    private function resolveCumplimiento(
        ?MetIndicator $indicator,
        ?float $resultado,
        float $metaEfectiva,
        MetDataLoadStatus $loadStatus,
        bool $hasTrackableLoadFields,
    ): ?float {
        if ($resultado === null || $metaEfectiva <= 0) {
            return null;
        }

        if (
            $hasTrackableLoadFields
            && ($loadStatus === MetDataLoadStatus::SinDatos || $loadStatus === MetDataLoadStatus::ErrorCalculo)
        ) {
            return null;
        }

        return app(MetCalculationService::class)->indicatorCompliancePercent($indicator, $resultado, $metaEfectiva);
    }
}
