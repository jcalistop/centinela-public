<?php

namespace App\Services\Metas;

use App\Enums\Metas\DataFieldInputType;
use App\Enums\Metas\MetDataLoadStatus;
use App\Enums\Metas\MetInstrumentPeriodo;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMonthlyData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MetIndicatorLastLoadedMonthService
{
    /**
     * Pool request-scoped de filas mensuales (SDD-183). Clave: ss|year|excludeUnits.
     * Filas: met_indicator_id, cen_center_id, mes, met_data_field_id (toBase).
     *
     * @var array<string, Collection<int, object{met_indicator_id: int, cen_center_id: int, mes: int|null, met_data_field_id: int}>>
     */
    private array $monthlyPoolByKey = [];

    /**
     * Indicadores cubiertos por el pool (evita falsos vacíos si el preload no incluye el indicador).
     *
     * @var array<string, array<int, true>>
     */
    private array $monthlyPoolIndicatorCoverage = [];

    public function __construct(
        private readonly MetFieldSsConfigService $fieldSsConfigService,
        private readonly MetIndicatorCalculationDataScopeService $dataScopeService,
    ) {}

    /**
     * Precarga filas de `met_monthly_data` para un conjunto de indicadores y centros (una query).
     * Expuesto para Fase B (SDD-184): el pool puede reutilizarse sin releer la tabla.
     *
     * @param  array<int, int>  $indicatorIds
     * @param  array<int, int>  $centerIds
     */
    public function preloadMonthlyPool(
        array $indicatorIds,
        array $centerIds,
        int $cenHieDependencyId,
        int $yearId,
        bool $excludeMeasurementUnitRows = true,
    ): void {
        $indicatorIds = array_values(array_unique(array_map('intval', $indicatorIds)));
        $centerIds = array_values(array_unique(array_map('intval', $centerIds)));

        if ($indicatorIds === [] || $centerIds === []) {
            return;
        }

        $key = $this->monthlyPoolKey($cenHieDependencyId, $yearId, $excludeMeasurementUnitRows);
        $coverage = $this->monthlyPoolIndicatorCoverage[$key] ?? [];
        $missingIndicators = array_values(array_filter(
            $indicatorIds,
            fn (int $id): bool => ! isset($coverage[$id]),
        ));

        if ($missingIndicators === []) {
            return;
        }

        $rows = $this->monthlyDataRowsQuery($cenHieDependencyId, $yearId, $excludeMeasurementUnitRows)
            ->whereIn('met_data_fields.met_indicator_id', $missingIndicators)
            ->whereIn('met_monthly_data.cen_center_id', $centerIds)
            ->toBase()
            ->get([
                'met_data_fields.met_indicator_id as met_indicator_id',
                'met_monthly_data.cen_center_id',
                'met_monthly_data.mes',
                'met_monthly_data.met_data_field_id',
                'met_monthly_data.met_measurement_unit_id',
                'met_monthly_data.valor',
            ]);

        if (isset($this->monthlyPoolByKey[$key])) {
            $this->monthlyPoolByKey[$key] = $this->monthlyPoolByKey[$key]->concat($rows)->values();
        } else {
            $this->monthlyPoolByKey[$key] = $rows;
        }

        foreach ($missingIndicators as $indicatorId) {
            $coverage[$indicatorId] = true;
        }
        $this->monthlyPoolIndicatorCoverage[$key] = $coverage;
    }

    public function forgetMonthlyPool(
        ?int $cenHieDependencyId = null,
        ?int $yearId = null,
        ?bool $excludeMeasurementUnitRows = null,
    ): void {
        if ($cenHieDependencyId === null || $yearId === null || $excludeMeasurementUnitRows === null) {
            $this->monthlyPoolByKey = [];
            $this->monthlyPoolIndicatorCoverage = [];

            return;
        }

        $key = $this->monthlyPoolKey($cenHieDependencyId, $yearId, $excludeMeasurementUnitRows);
        unset($this->monthlyPoolByKey[$key], $this->monthlyPoolIndicatorCoverage[$key]);
    }

    public function monthlyPoolCoversIndicator(
        int $indicatorId,
        int $cenHieDependencyId,
        int $yearId,
        bool $excludeMeasurementUnitRows = true,
    ): bool {
        $key = $this->monthlyPoolKey($cenHieDependencyId, $yearId, $excludeMeasurementUnitRows);

        return isset($this->monthlyPoolIndicatorCoverage[$key][$indicatorId]);
    }

    /**
     * Filas del pool para un set de indicadores (SDD-184). Null si algún indicador no está cubierto.
     *
     * @param  array<int, int>  $indicatorIds
     * @return Collection<int, object{met_indicator_id: int, cen_center_id: int, mes: int|null, met_data_field_id: int, met_measurement_unit_id: int|null, valor: mixed}>|null
     */
    public function pooledMonthlyRowsForIndicators(
        array $indicatorIds,
        int $cenHieDependencyId,
        int $yearId,
        bool $excludeMeasurementUnitRows = true,
    ): ?Collection {
        $indicatorIds = array_values(array_unique(array_map('intval', $indicatorIds)));
        if ($indicatorIds === []) {
            return collect();
        }

        foreach ($indicatorIds as $indicatorId) {
            if (! $this->monthlyPoolCoversIndicator($indicatorId, $cenHieDependencyId, $yearId, $excludeMeasurementUnitRows)) {
                return null;
            }
        }

        $key = $this->monthlyPoolKey($cenHieDependencyId, $yearId, $excludeMeasurementUnitRows);
        $allowed = array_flip($indicatorIds);

        return $this->monthlyPoolByKey[$key]
            ->filter(fn ($row): bool => isset($allowed[(int) $row->met_indicator_id]))
            ->values();
    }

    /**
     * Último mes con dato cargado por indicador (máximo `mes` en `met_monthly_data` del SS y año).
     *
     * @param  array<int, int>  $indicatorIds
     * @return Collection<int, int> mapa indicatorId => mes (1–12)
     */
    public function lastMonthByIndicatorIds(
        array $indicatorIds,
        int $cenHieDependencyId,
        int $yearId,
        bool $excludeMeasurementUnitRows = true,
    ): Collection {
        if ($indicatorIds === []) {
            return collect();
        }

        return $this->monthlyDataBaseQuery($cenHieDependencyId, $yearId, $excludeMeasurementUnitRows)
            ->whereIn('met_data_fields.met_indicator_id', $indicatorIds)
            ->selectRaw('met_data_fields.met_indicator_id as met_indicator_id, MAX(met_monthly_data.mes) as ultimo_mes')
            ->groupBy('met_data_fields.met_indicator_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->met_indicator_id => (int) $row->ultimo_mes]);
    }

    /**
     * @param  array<int, int>  $centerIds
     * @return Collection<int, int> mapa centerId => mes (1–12)
     */
    public function lastMonthByIndicatorAndCenterIds(
        int $indicatorId,
        array $centerIds,
        int $cenHieDependencyId,
        int $yearId,
        bool $excludeMeasurementUnitRows = true,
    ): Collection {
        if ($centerIds === []) {
            return collect();
        }

        return $this->monthlyDataBaseQuery($cenHieDependencyId, $yearId, $excludeMeasurementUnitRows)
            ->where('met_data_fields.met_indicator_id', $indicatorId)
            ->whereIn('met_monthly_data.cen_center_id', $centerIds)
            ->selectRaw('met_monthly_data.cen_center_id as cen_center_id, MAX(met_monthly_data.mes) as ultimo_mes')
            ->groupBy('met_monthly_data.cen_center_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->cen_center_id => (int) $row->ultimo_mes]);
    }

    /**
     * @param  array<int, int>  $indicatorIds
     * @return Collection<int, int> mapa indicatorId => mes (1–12)
     */
    public function lastMonthByCenterAndIndicatorIds(
        int $centerId,
        array $indicatorIds,
        int $cenHieDependencyId,
        int $yearId,
        bool $excludeMeasurementUnitRows = true,
    ): Collection {
        if ($indicatorIds === []) {
            return collect();
        }

        return $this->monthlyDataBaseQuery($cenHieDependencyId, $yearId, $excludeMeasurementUnitRows)
            ->where('met_monthly_data.cen_center_id', $centerId)
            ->whereIn('met_data_fields.met_indicator_id', $indicatorIds)
            ->selectRaw('met_data_fields.met_indicator_id as met_indicator_id, MAX(met_monthly_data.mes) as ultimo_mes')
            ->groupBy('met_data_fields.met_indicator_id')
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->met_indicator_id => (int) $row->ultimo_mes]);
    }

    /**
     * @param  array<int, int>  $centerIds
     * @param  Collection<int, array{count: int, periodos: array<int, string>, message: string}>  $calculationErrorsByCenterId
     * @param  Collection<int, MetIndicatorConfiguration>|null  $configsByCenterId
     * @return Collection<int, array{ultimo_mes_cargado: int|null, estado_carga: MetDataLoadStatus}>
     */
    public function snapshotsByIndicatorAndCenterIds(
        MetIndicator $indicator,
        array $centerIds,
        int $cenHieDependencyId,
        int $yearId,
        bool $excludeMeasurementUnitRows = true,
        ?Collection $calculationErrorsByCenterId = null,
        ?Collection $configsByCenterId = null,
    ): Collection {
        if ($centerIds === []) {
            return collect();
        }

        $indicator->loadMissing('dataFields');

        $effectiveFields = $this->fieldSsConfigService->applyEffectiveTypes(
            $indicator->dataFields,
            $cenHieDependencyId,
            $yearId,
        );

        $errorsByCenter = $calculationErrorsByCenterId ?? collect();

        if ($configsByCenterId !== null && $configsByCenterId->isNotEmpty()) {
            $scopeIdsByCenter = [];
            $allScopeIds = [];

            foreach ($centerIds as $centerId) {
                $centerId = (int) $centerId;
                $config = $configsByCenterId->get($centerId);

                if ($config instanceof MetIndicatorConfiguration) {
                    $measurementUnitId = $config->met_measurement_unit_id !== null
                        ? (int) $config->met_measurement_unit_id
                        : null;

                    $scopeIds = $this->dataScopeService->allDataCenterIds(
                        $indicator,
                        $centerId,
                        $config,
                        $yearId,
                        $cenHieDependencyId,
                        $measurementUnitId,
                    );
                    $scopeIdsByCenter[$centerId] = $scopeIds;
                    foreach ($scopeIds as $scopeId) {
                        $allScopeIds[] = (int) $scopeId;
                    }
                }
            }

            if (! $this->monthlyPoolCoversIndicator(
                (int) $indicator->id,
                $cenHieDependencyId,
                $yearId,
                $excludeMeasurementUnitRows,
            ) && $allScopeIds !== []) {
                $this->preloadMonthlyPool(
                    [(int) $indicator->id],
                    array_values(array_unique($allScopeIds)),
                    $cenHieDependencyId,
                    $yearId,
                    $excludeMeasurementUnitRows,
                );
            }

            return collect($centerIds)->mapWithKeys(function (int $centerId) use (
                $indicator,
                $configsByCenterId,
                $cenHieDependencyId,
                $yearId,
                $effectiveFields,
                $excludeMeasurementUnitRows,
                $errorsByCenter,
                $scopeIdsByCenter,
            ): array {
                $config = $configsByCenterId->get($centerId);

                if ($config instanceof MetIndicatorConfiguration) {
                    return [
                        $centerId => $this->buildScopedLoadSnapshot(
                            $indicator->id,
                            $scopeIdsByCenter[$centerId] ?? [$centerId],
                            $cenHieDependencyId,
                            $yearId,
                            $effectiveFields,
                            $excludeMeasurementUnitRows,
                            $errorsByCenter->has($centerId),
                        ),
                    ];
                }

                return [
                    $centerId => $this->buildSingleCenterLoadSnapshot(
                        $indicator->id,
                        $centerId,
                        $cenHieDependencyId,
                        $yearId,
                        $effectiveFields,
                        $excludeMeasurementUnitRows,
                        $errorsByCenter->has($centerId),
                    ),
                ];
            });
        }

        $lastMonths = $this->lastMonthByIndicatorAndCenterIds(
            $indicator->id,
            $centerIds,
            $cenHieDependencyId,
            $yearId,
            $excludeMeasurementUnitRows,
        );

        $loadedFieldsByCenter = $this->loadedRequiredFieldIdsByCenter(
            $indicator->id,
            $centerIds,
            $cenHieDependencyId,
            $yearId,
            $effectiveFields,
            $lastMonths,
            $excludeMeasurementUnitRows,
        );

        return collect($centerIds)->mapWithKeys(function (int $centerId) use (
            $lastMonths,
            $loadedFieldsByCenter,
            $effectiveFields,
            $errorsByCenter,
        ): array {
            $lastMonth = $lastMonths->get($centerId);
            $loadedFieldIds = $loadedFieldsByCenter->get($centerId, []);

            return [
                $centerId => [
                    'ultimo_mes_cargado' => $lastMonth,
                    'estado_carga' => $this->resolveLoadStatus(
                        $lastMonth,
                        $effectiveFields,
                        $loadedFieldIds,
                        $errorsByCenter->has($centerId),
                    ),
                ],
            ];
        });
    }

    /**
     * @param  iterable<int, MetIndicator>  $indicators
     * @param  callable(int $indicatorId, array<int, int> $centerIds): Collection<int, array{count: int, periodos: array<int, string>, message: string}>  $calculationErrorsResolver
     * @param  Collection<int, MetIndicatorConfiguration>|null  $configsByIndicatorId  configuración del centro presentado por indicador; sin ella el estado se resuelve solo con datos locales (SDD-190)
     * @return Collection<int, array{ultimo_mes_cargado: int|null, estado_carga: MetDataLoadStatus}>
     */
    public function snapshotsByCenterAndIndicators(
        int $centerId,
        iterable $indicators,
        int $cenHieDependencyId,
        int $yearId,
        bool $excludeMeasurementUnitRows = true,
        ?callable $calculationErrorsResolver = null,
        ?Collection $configsByIndicatorId = null,
    ): Collection {
        $indicatorList = collect($indicators)->values();

        if ($indicatorList->isEmpty()) {
            return collect();
        }

        $indicatorIds = $indicatorList
            ->map(fn (MetIndicator $indicator): int => (int) $indicator->id)
            ->all();

        $this->preloadMonthlyPool(
            $indicatorIds,
            $this->dataCenterIdsForIndicators(
                $indicatorList,
                $centerId,
                $configsByIndicatorId,
                $yearId,
                $cenHieDependencyId,
            ),
            $cenHieDependencyId,
            $yearId,
            $excludeMeasurementUnitRows,
        );

        return $indicatorList->mapWithKeys(function (MetIndicator $indicator) use (
            $centerId,
            $cenHieDependencyId,
            $yearId,
            $excludeMeasurementUnitRows,
            $calculationErrorsResolver,
            $configsByIndicatorId,
        ): array {
            $errorsByCenter = $calculationErrorsResolver !== null
                ? $calculationErrorsResolver($indicator->id, [$centerId])
                : collect();

            $config = $configsByIndicatorId?->get((int) $indicator->id);

            $snapshot = $this->snapshotsByIndicatorAndCenterIds(
                $indicator,
                [$centerId],
                $cenHieDependencyId,
                $yearId,
                $excludeMeasurementUnitRows,
                $errorsByCenter,
                $config instanceof MetIndicatorConfiguration
                    ? collect([$centerId => $config])
                    : null,
            )->get($centerId, [
                'ultimo_mes_cargado' => null,
                'estado_carga' => MetDataLoadStatus::SinDatos,
            ]);

            return [(int) $indicator->id => $snapshot];
        });
    }

    /**
     * Universo de centros que alimentan el estado de carga del centro presentado (SDD-190).
     *
     * @param  Collection<int, MetIndicator>  $indicators
     * @param  Collection<int, MetIndicatorConfiguration>|null  $configsByIndicatorId
     * @return array<int, int>
     */
    private function dataCenterIdsForIndicators(
        Collection $indicators,
        int $centerId,
        ?Collection $configsByIndicatorId,
        int $yearId,
        int $cenHieDependencyId,
    ): array {
        $centerIds = [$centerId];

        if ($configsByIndicatorId === null) {
            return $centerIds;
        }

        foreach ($indicators as $indicator) {
            $config = $configsByIndicatorId->get((int) $indicator->id);

            if (! $config instanceof MetIndicatorConfiguration) {
                continue;
            }

            foreach (
                $this->dataScopeService->allDataCenterIds(
                    $indicator,
                    $centerId,
                    $config,
                    $yearId,
                    $cenHieDependencyId,
                    $config->met_measurement_unit_id !== null ? (int) $config->met_measurement_unit_id : null,
                ) as $scopeCenterId
            ) {
                $centerIds[] = (int) $scopeCenterId;
            }
        }

        return array_values(array_unique($centerIds));
    }

    public function etiquetaMes(?int $mes): string
    {
        if ($mes === null || $mes < 1 || $mes > 12) {
            return 'Sin datos';
        }

        $periodo = MetInstrumentPeriodo::tryFrom(sprintf('MES-%02d', $mes));

        return $periodo?->etiquetaCorta() ?? 'Sin datos';
    }

    /**
     * @param  array<int, int>  $dataCenterIds
     * @param  Collection<int, MetDataField>  $effectiveFields
     * @return array{ultimo_mes_cargado: int|null, estado_carga: MetDataLoadStatus}
     */
    private function buildScopedLoadSnapshot(
        int $indicatorId,
        array $dataCenterIds,
        int $cenHieDependencyId,
        int $yearId,
        Collection $effectiveFields,
        bool $excludeMeasurementUnitRows,
        bool $hasCalculationError,
    ): array {
        if ($dataCenterIds === []) {
            return [
                'ultimo_mes_cargado' => null,
                'estado_carga' => $hasCalculationError
                    ? MetDataLoadStatus::ErrorCalculo
                    : MetDataLoadStatus::SinDatos,
            ];
        }

        $allowedCenters = array_flip(array_map('intval', $dataCenterIds));

        if ($this->monthlyPoolCoversIndicator($indicatorId, $cenHieDependencyId, $yearId, $excludeMeasurementUnitRows)) {
            $poolKey = $this->monthlyPoolKey($cenHieDependencyId, $yearId, $excludeMeasurementUnitRows);
            $rows = $this->monthlyPoolByKey[$poolKey]->filter(
                fn ($row): bool => (int) $row->met_indicator_id === $indicatorId
                    && isset($allowedCenters[(int) $row->cen_center_id]),
            )->values();
        } else {
            $rows = $this->monthlyDataRowsQuery($cenHieDependencyId, $yearId, $excludeMeasurementUnitRows)
                ->where('met_data_fields.met_indicator_id', $indicatorId)
                ->whereIn('met_monthly_data.cen_center_id', $dataCenterIds)
                ->toBase()
                ->get([
                    'met_data_fields.met_indicator_id as met_indicator_id',
                    'met_monthly_data.cen_center_id',
                    'met_monthly_data.mes',
                    'met_monthly_data.met_data_field_id',
                ]);
        }

        $lastMonth = $rows
            ->filter(fn ($row): bool => $row->mes !== null)
            ->map(fn ($row): int => (int) $row->mes)
            ->max();

        $lastMonth = $lastMonth !== null ? (int) $lastMonth : null;

        $loaded = [];

        foreach ($effectiveFields as $field) {
            if ($this->fieldHasLoadedValueInPool($field, $rows, $lastMonth)) {
                $loaded[] = (int) $field->id;
            }
        }

        return [
            'ultimo_mes_cargado' => $lastMonth,
            'estado_carga' => $this->resolveLoadStatus(
                $lastMonth,
                $effectiveFields,
                $loaded,
                $hasCalculationError,
            ),
        ];
    }

    /**
     * @param  Collection<int, MetDataField>  $effectiveFields
     * @return array{ultimo_mes_cargado: int|null, estado_carga: MetDataLoadStatus}
     */
    private function buildSingleCenterLoadSnapshot(
        int $indicatorId,
        int $centerId,
        int $cenHieDependencyId,
        int $yearId,
        Collection $effectiveFields,
        bool $excludeMeasurementUnitRows,
        bool $hasCalculationError,
    ): array {
        $lastMonths = $this->lastMonthByIndicatorAndCenterIds(
            $indicatorId,
            [$centerId],
            $cenHieDependencyId,
            $yearId,
            $excludeMeasurementUnitRows,
        );

        $loadedFieldsByCenter = $this->loadedRequiredFieldIdsByCenter(
            $indicatorId,
            [$centerId],
            $cenHieDependencyId,
            $yearId,
            $effectiveFields,
            $lastMonths,
            $excludeMeasurementUnitRows,
        );

        $lastMonth = $lastMonths->get($centerId);
        $loadedFieldIds = $loadedFieldsByCenter->get($centerId, []);

        return [
            'ultimo_mes_cargado' => $lastMonth,
            'estado_carga' => $this->resolveLoadStatus(
                $lastMonth,
                $effectiveFields,
                $loadedFieldIds,
                $hasCalculationError,
            ),
        ];
    }

    /**
     * @param  Collection<int, MetMonthlyData|object{cen_center_id: int, mes: int|null, met_data_field_id: int}>  $poolRows
     */
    private function fieldHasLoadedValueInPool(
        MetDataField $field,
        Collection $poolRows,
        ?int $lastMonth,
    ): bool {
        $fieldId = (int) $field->id;
        $inputType = $field->input_type instanceof DataFieldInputType
            ? $field->input_type
            : DataFieldInputType::from((string) $field->input_type);

        if ($inputType === DataFieldInputType::LineaBase) {
            return $poolRows->contains(
                fn ($row): bool => (int) $row->met_data_field_id === $fieldId && $row->mes === null,
            );
        }

        if ($lastMonth === null) {
            return false;
        }

        return $poolRows->contains(
            fn ($row): bool => (int) $row->met_data_field_id === $fieldId && (int) $row->mes === $lastMonth,
        );
    }

    /**
     * @param  array<int, int>  $centerIds
     * @param  Collection<int, MetDataField>  $effectiveFields
     * @param  Collection<int, int>  $lastMonthsByCenter
     * @return Collection<int, array<int, int>>
     */
    private function loadedRequiredFieldIdsByCenter(
        int $indicatorId,
        array $centerIds,
        int $cenHieDependencyId,
        int $yearId,
        Collection $effectiveFields,
        Collection $lastMonthsByCenter,
        bool $excludeMeasurementUnitRows,
    ): Collection {
        if ($centerIds === [] || $effectiveFields->isEmpty()) {
            return collect();
        }

        $rows = $this->monthlyDataRowsQuery($cenHieDependencyId, $yearId, $excludeMeasurementUnitRows)
            ->where('met_data_fields.met_indicator_id', $indicatorId)
            ->whereIn('met_monthly_data.cen_center_id', $centerIds)
            ->get(['met_monthly_data.cen_center_id', 'met_monthly_data.mes', 'met_monthly_data.met_data_field_id']);

        $rowsByCenter = $rows->groupBy(fn ($row): int => (int) $row->cen_center_id);

        return collect($centerIds)->mapWithKeys(function (int $centerId) use (
            $rowsByCenter,
            $effectiveFields,
            $lastMonthsByCenter,
        ): array {
            $centerRows = $rowsByCenter->get($centerId, collect());
            $lastMonth = $lastMonthsByCenter->get($centerId);
            $loaded = [];

            foreach ($effectiveFields as $field) {
                if ($this->fieldHasLoadedValue($field, $centerRows, $lastMonth)) {
                    $loaded[] = (int) $field->id;
                }
            }

            return [$centerId => $loaded];
        });
    }

    /**
     * @param  Collection<int, MetMonthlyData|object{cen_center_id: int, mes: int|null, met_data_field_id: int}>  $centerRows
     */
    private function fieldHasLoadedValue(
        MetDataField $field,
        Collection $centerRows,
        ?int $lastMonth,
    ): bool {
        $fieldId = (int) $field->id;
        $inputType = $field->input_type instanceof DataFieldInputType
            ? $field->input_type
            : DataFieldInputType::from((string) $field->input_type);

        if ($inputType === DataFieldInputType::LineaBase) {
            return $centerRows->contains(
                fn ($row): bool => (int) $row->met_data_field_id === $fieldId && $row->mes === null,
            );
        }

        if ($lastMonth === null) {
            return false;
        }

        return $centerRows->contains(
            fn ($row): bool => (int) $row->met_data_field_id === $fieldId && (int) $row->mes === $lastMonth,
        );
    }

    /**
     * @param  array<int, int>  $loadedRequiredFieldIds
     */
    private function resolveLoadStatus(
        ?int $lastMonth,
        Collection $effectiveFields,
        array $loadedRequiredFieldIds,
        bool $hasCalculationError,
    ): MetDataLoadStatus {
        if ($hasCalculationError) {
            return MetDataLoadStatus::ErrorCalculo;
        }

        $requiredFieldIds = $effectiveFields
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($requiredFieldIds === []) {
            return $lastMonth !== null || $loadedRequiredFieldIds !== []
                ? MetDataLoadStatus::Cargado
                : MetDataLoadStatus::SinDatos;
        }

        if ($loadedRequiredFieldIds === []) {
            return MetDataLoadStatus::SinDatos;
        }

        $missingFields = array_diff($requiredFieldIds, $loadedRequiredFieldIds);

        return $missingFields === []
            ? MetDataLoadStatus::Cargado
            : MetDataLoadStatus::Parcial;
    }

    private function monthlyDataRowsQuery(
        int $cenHieDependencyId,
        int $yearId,
        bool $excludeMeasurementUnitRows,
    ): Builder {
        $query = MetMonthlyData::query()
            ->join('met_data_fields', 'met_monthly_data.met_data_field_id', '=', 'met_data_fields.id')
            ->where('met_monthly_data.year_id', $yearId)
            ->where('met_monthly_data.cen_hie_dependency_id', $cenHieDependencyId)
            ->whereNotNull('met_monthly_data.valor');

        if ($excludeMeasurementUnitRows) {
            $query->whereNull('met_monthly_data.met_measurement_unit_id');
        }

        return $query;
    }

    private function monthlyDataBaseQuery(
        int $cenHieDependencyId,
        int $yearId,
        bool $excludeMeasurementUnitRows,
    ): Builder {
        $query = MetMonthlyData::query()
            ->join('met_data_fields', 'met_monthly_data.met_data_field_id', '=', 'met_data_fields.id')
            ->where('met_monthly_data.year_id', $yearId)
            ->where('met_monthly_data.cen_hie_dependency_id', $cenHieDependencyId)
            ->whereNotNull('met_monthly_data.valor')
            ->whereNotNull('met_monthly_data.mes');

        if ($excludeMeasurementUnitRows) {
            $query->whereNull('met_monthly_data.met_measurement_unit_id');
        }

        return $query;
    }

    private function monthlyPoolKey(int $cenHieDependencyId, int $yearId, bool $excludeMeasurementUnitRows): string
    {
        return $cenHieDependencyId.'|'.$yearId.'|'.($excludeMeasurementUnitRows ? '1' : '0');
    }
}
