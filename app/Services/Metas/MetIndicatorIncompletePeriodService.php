<?php

namespace App\Services\Metas;

use App\Enums\Metas\DataFieldInputType;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetMonthlyData;
use Illuminate\Support\Collection;

class MetIndicatorIncompletePeriodService
{
    public function __construct(
        private readonly MetPeriodCaptureCompletenessValidator $completenessValidator,
        private readonly MetFieldSsConfigService $fieldSsConfigService,
        private readonly MetIndicatorLastLoadedMonthService $lastLoadedMonthService,
    ) {}

    /**
     * @param  array<int, int>  $indicatorIds
     * @return Collection<int, int>
     */
    public function indicatorIdsWithIncompletePeriods(
        array $indicatorIds,
        int $cenHieDependencyId,
        int $yearId,
    ): Collection {
        if ($indicatorIds === []) {
            return collect();
        }

        $indicators = MetIndicator::query()
            ->whereIn('id', $indicatorIds)
            ->with('dataFields')
            ->get();

        if ($indicators->isEmpty()) {
            return collect();
        }

        $allFields = $indicators->flatMap(
            fn (MetIndicator $indicator): Collection => $indicator->dataFields,
        )->values();

        $effectiveFields = $this->fieldSsConfigService
            ->applyEffectiveTypes($allFields, $cenHieDependencyId, $yearId)
            ->filter(
                fn ($field): bool => $field->input_type !== DataFieldInputType::LineaBase,
            );

        $effectiveByIndicator = $effectiveFields->groupBy(
            fn ($field): int => (int) $field->met_indicator_id,
        );

        $fieldIds = $effectiveFields->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all();

        if ($fieldIds === []) {
            return collect();
        }

        $records = MetMonthlyData::query()
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->where('year_id', $yearId)
            ->whereNotNull('mes')
            ->whereIn('met_data_field_id', $fieldIds)
            ->get();

        $fieldToIndicatorId = $effectiveFields
            ->mapWithKeys(fn ($field): array => [(int) $field->id => (int) $field->met_indicator_id]);

        $recordsByIndicator = $records->groupBy(
            fn (MetMonthlyData $record): int => (int) ($fieldToIndicatorId[(int) $record->met_data_field_id] ?? 0),
        );

        $found = collect();

        foreach ($effectiveByIndicator as $indicatorId => $fieldsForIndicator) {
            $indicatorRecords = $recordsByIndicator->get((int) $indicatorId, collect());

            if ($this->incompletePeriodsFromRecords(
                $fieldsForIndicator,
                $indicatorRecords,
                $cenHieDependencyId,
                $yearId,
            )->isNotEmpty()) {
                $found->push((int) $indicatorId);
            }
        }

        return $found->unique()->values();
    }

    public function hasIncompletePeriodsForIndicator(
        int $indicatorId,
        int $cenHieDependencyId,
        int $yearId,
    ): bool {
        return $this->indicatorIdsWithIncompletePeriods(
            [$indicatorId],
            $cenHieDependencyId,
            $yearId,
        )->isNotEmpty();
    }

    /**
     * @return array{message: string, center_count: int, period_count: int}|null
     */
    public function summaryForIndicator(
        int $indicatorId,
        int $cenHieDependencyId,
        int $yearId,
    ): ?array {
        $byCenter = $this->incompletePeriodsByCenterId($indicatorId, $cenHieDependencyId, $yearId);

        if ($byCenter->isEmpty()) {
            return null;
        }

        $periodCount = $byCenter->sum(fn (array $entry): int => $entry['count']);

        return [
            'message' => 'Meses con captura parcial — complete o vacíe cada período',
            'center_count' => $byCenter->count(),
            'period_count' => $periodCount,
        ];
    }

    /**
     * @param  Collection<int, MetIndicator>  $indicators
     * @return list<array{severity: string, title: string, body: string, url: string}>
     */
    public function dashboardAlertsForIndicators(
        Collection $indicators,
        int $cenHieDependencyId,
        int $yearId,
    ): array {
        if ($indicators->isEmpty()) {
            return [];
        }

        $indicatorIds = $indicators->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $incompleteIds = $this->indicatorIdsWithIncompletePeriods(
            $indicatorIds,
            $cenHieDependencyId,
            $yearId,
        )->flip();

        $alerts = [];

        foreach ($indicators as $indicator) {
            if (! isset($incompleteIds[(int) $indicator->id])) {
                continue;
            }

            $summary = $this->summaryForIndicator((int) $indicator->id, $cenHieDependencyId, $yearId);
            if ($summary === null) {
                continue;
            }

            $type = $indicator->indicatorType;
            if ($type === null) {
                continue;
            }

            $url = route('metas.instrument.indicators.show', [
                'indicatorType' => $type,
                'indicator' => $indicator,
            ]).'#tab-carga-datos';

            $body = 'Carga incompleta — '.$summary['message'];
            if ($summary['center_count'] > 1) {
                $body .= ' ('.$summary['center_count'].' establecimientos)';
            }

            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Metas: '.$indicator->clave,
                'body' => $body,
                'url' => $url,
            ];
        }

        return $alerts;
    }

    /**
     * @return list<int>
     */
    public function incompleteMonthNumbersForCenter(
        int $indicatorId,
        int $centerId,
        int $cenHieDependencyId,
        int $yearId,
        ?int $measurementUnitId = null,
    ): array {
        $indicator = MetIndicator::query()
            ->with('dataFields')
            ->find($indicatorId);

        if ($indicator === null) {
            return [];
        }

        $effectiveFields = $this->fieldSsConfigService->applyEffectiveTypes(
            $indicator->dataFields,
            $cenHieDependencyId,
            $yearId,
        )->filter(
            fn ($field): bool => $field->input_type !== DataFieldInputType::LineaBase,
        );

        if ($effectiveFields->isEmpty()) {
            return [];
        }

        $fieldIds = $effectiveFields->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $query = MetMonthlyData::query()
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->where('year_id', $yearId)
            ->where('cen_center_id', $centerId)
            ->whereNotNull('mes')
            ->whereIn('met_data_field_id', $fieldIds);

        if ($measurementUnitId !== null) {
            $query->where('met_measurement_unit_id', $measurementUnitId);
        } else {
            $query->whereNull('met_measurement_unit_id');
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            return [];
        }

        $grouped = $records->groupBy(
            fn (MetMonthlyData $record): string => (string) (int) $record->mes,
        );

        $incompleteMonths = [];

        foreach ($grouped as $mesKey => $groupRecords) {
            $mes = (int) $mesKey;

            $applicableFields = $this->completenessValidator->applicableFieldsForMonth(
                $effectiveFields,
                $mes,
                $cenHieDependencyId,
                $yearId,
            );

            if ($applicableFields->isEmpty()) {
                continue;
            }

            $valuesByFieldId = [];
            foreach ($applicableFields as $field) {
                $record = $groupRecords->firstWhere('met_data_field_id', $field->id);
                $valuesByFieldId[$field->id] = $record?->valor;
            }

            if ($this->completenessValidator->isPeriodIncomplete($valuesByFieldId, $applicableFields)) {
                $incompleteMonths[] = $mes;
            }
        }

        return array_values(array_unique($incompleteMonths));
    }

    /**
     * Detalle de períodos incompletos por centro para un conjunto de indicadores (SDD-184).
     *
     * @param  Collection<int, MetIndicator>  $indicators  con `dataFields` preferiblemente cargados
     * @param  array<int, int>  $centerIds
     * @return Collection<int, Collection<int, array{count: int, periodos: array<int, string>, message: string}>>
     */
    public function incompletePeriodsByIndicators(
        Collection $indicators,
        int $cenHieDependencyId,
        int $yearId,
        array $centerIds = [],
        bool $excludeMeasurementUnitRows = true,
    ): Collection {
        if ($indicators->isEmpty()) {
            return collect();
        }

        $indicators->loadMissing('dataFields');

        $allFields = $indicators->flatMap(
            fn (MetIndicator $indicator): Collection => $indicator->dataFields,
        )->values();

        $effectiveFields = $this->fieldSsConfigService
            ->applyEffectiveTypes($allFields, $cenHieDependencyId, $yearId)
            ->filter(
                fn ($field): bool => $field->input_type !== DataFieldInputType::LineaBase,
            );

        $effectiveByIndicator = $effectiveFields->groupBy(
            fn ($field): int => (int) $field->met_indicator_id,
        );

        $fieldIds = $effectiveFields->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all();

        $empty = $indicators->mapWithKeys(
            fn (MetIndicator $indicator): array => [(int) $indicator->id => collect()],
        );

        if ($fieldIds === []) {
            return $empty;
        }

        $indicatorIds = $indicators->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $records = $this->resolveMonthlyRecordsForIncompletePeriods(
            $indicatorIds,
            $fieldIds,
            $cenHieDependencyId,
            $yearId,
            $centerIds,
            $excludeMeasurementUnitRows,
        );

        $fieldToIndicatorId = $effectiveFields->mapWithKeys(
            fn ($field): array => [(int) $field->id => (int) $field->met_indicator_id],
        );

        $recordsByIndicator = $records->groupBy(
            fn ($record): int => (int) ($fieldToIndicatorId[(int) $record->met_data_field_id] ?? 0),
        );

        $result = collect();

        foreach ($indicators as $indicator) {
            $indicatorId = (int) $indicator->id;
            $fieldsForIndicator = $effectiveByIndicator->get($indicatorId, collect());
            $indicatorRecords = $recordsByIndicator->get($indicatorId, collect());

            $result->put(
                $indicatorId,
                $this->incompletePeriodsFromRecords(
                    $fieldsForIndicator,
                    $indicatorRecords,
                    $cenHieDependencyId,
                    $yearId,
                    $centerIds,
                ),
            );
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $centerIds
     * @return Collection<int, array{count: int, periodos: array<int, string>, message: string}>
     */
    public function incompletePeriodsByCenterId(
        int $indicatorId,
        int $cenHieDependencyId,
        int $yearId,
        array $centerIds = [],
    ): Collection {
        $indicator = MetIndicator::query()
            ->with(['dataFields', 'indicatorType'])
            ->find($indicatorId);

        if ($indicator === null) {
            return collect();
        }

        $excludeUnits = ! $indicator->indicatorType?->usesInternalUnitMeasurement();

        return $this->incompletePeriodsByIndicators(
            new \Illuminate\Database\Eloquent\Collection([$indicator]),
            $cenHieDependencyId,
            $yearId,
            $centerIds,
            $excludeUnits,
        )->get($indicatorId, collect());
    }

    /**
     * @param  array<int, int>  $indicatorIds
     * @param  array<int, int>  $fieldIds
     * @param  array<int, int>  $centerIds
     * @return Collection<int, MetMonthlyData|object>
     */
    private function resolveMonthlyRecordsForIncompletePeriods(
        array $indicatorIds,
        array $fieldIds,
        int $cenHieDependencyId,
        int $yearId,
        array $centerIds,
        bool $excludeMeasurementUnitRows,
    ): Collection {
        $pooled = $this->lastLoadedMonthService->pooledMonthlyRowsForIndicators(
            $indicatorIds,
            $cenHieDependencyId,
            $yearId,
            $excludeMeasurementUnitRows,
        );

        if ($pooled !== null) {
            $allowedFields = array_flip($fieldIds);

            return $pooled
                ->filter(function ($row) use ($allowedFields): bool {
                    return isset($allowedFields[(int) $row->met_data_field_id])
                        && $row->mes !== null;
                })
                ->values();
        }

        $query = MetMonthlyData::query()
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->where('year_id', $yearId)
            ->whereNotNull('mes')
            ->whereIn('met_data_field_id', $fieldIds);

        if ($centerIds !== []) {
            $query->whereIn('cen_center_id', $centerIds);
        }

        if ($excludeMeasurementUnitRows) {
            $query->whereNull('met_measurement_unit_id');
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, MetDataField>  $effectiveFields
     * @param  Collection<int, MetMonthlyData|object>  $records
     * @param  array<int, int>  $centerIds
     * @return Collection<int, array{count: int, periodos: array<int, string>, message: string}>
     */
    private function incompletePeriodsFromRecords(
        Collection $effectiveFields,
        Collection $records,
        int $cenHieDependencyId,
        int $yearId,
        array $centerIds = [],
    ): Collection {
        if ($records->isEmpty() || $effectiveFields->isEmpty()) {
            return collect();
        }

        if ($centerIds !== []) {
            $allowed = array_flip($centerIds);
            $records = $records->filter(
                fn ($record): bool => isset($allowed[(int) $record->cen_center_id]),
            );
        }

        if ($records->isEmpty()) {
            return collect();
        }

        $grouped = $records->groupBy(
            fn ($record): string => implode(':', [
                (int) $record->cen_center_id,
                (int) $record->mes,
                (int) ($record->met_measurement_unit_id ?? 0),
            ]),
        );

        $incompleteByCenter = [];

        foreach ($grouped as $groupRecords) {
            $first = $groupRecords->first();
            $mes = (int) $first->mes;
            $centerId = (int) $first->cen_center_id;

            $applicableFields = $this->completenessValidator->applicableFieldsForMonth(
                $effectiveFields,
                $mes,
                $cenHieDependencyId,
                $yearId,
            );

            if ($applicableFields->isEmpty()) {
                continue;
            }

            $valuesByFieldId = [];
            foreach ($applicableFields as $field) {
                $record = $groupRecords->firstWhere('met_data_field_id', $field->id);
                $valuesByFieldId[$field->id] = $record?->valor ?? null;
            }

            if (! $this->completenessValidator->isPeriodIncomplete($valuesByFieldId, $applicableFields)) {
                continue;
            }

            if (! isset($incompleteByCenter[$centerId])) {
                $incompleteByCenter[$centerId] = [
                    'count' => 0,
                    'periodos' => [],
                    'message' => 'Meses con captura parcial',
                ];
            }

            $incompleteByCenter[$centerId]['count']++;
            $incompleteByCenter[$centerId]['periodos'][] = $this->lastLoadedMonthService->etiquetaMes($mes);
        }

        return collect($incompleteByCenter)->map(function (array $entry): array {
            $entry['periodos'] = array_values(array_unique($entry['periodos']));

            return $entry;
        });
    }
}
