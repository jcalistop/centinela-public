<?php

namespace App\Services;

use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\DataFieldInputType;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetMonthlyData;
use App\Services\Metas\MetFieldSsConfigService;
use App\Services\Metas\MetIndicatorLastLoadedMonthService;
use App\Services\Metas\MetIndicatorPeriodicityResolver;
use Illuminate\Support\Collection;

class MetDataAggregatorService
{
    public function __construct(
        private MetIndicatorPeriodicityResolver $periodicityResolver,
        private MetFieldSsConfigService $fieldSsConfigService,
        private MetIndicatorLastLoadedMonthService $lastLoadedMonthService,
    ) {}

    /**
     * @param  array<int, int>  $adosadoCenterIds  IDs adicionales de centros adosados cuya data se suma al centro madre
     * @return array<string, float|null>
     */
    public function aggregateVariables(
        MetIndicator $indicator,
        int $centerId,
        int $yearId,
        string $periodo,
        array $adosadoCenterIds = [],
        ?int $measurementUnitId = null,
        ?int $cenHieDependencyId = null,
    ): array {
        $indicator->loadMissing(['dataFields', 'indicatorType']);
        $fields = $indicator->dataFields;

        if ($fields->isEmpty()) {
            return [];
        }

        if ($cenHieDependencyId !== null) {
            $fields = $this->fieldSsConfigService->applyEffectiveTypes(
                $fields,
                $cenHieDependencyId,
                $yearId,
            );
        }

        $months = $this->resolveMonths(
            $indicator,
            $periodo,
            $cenHieDependencyId ?? 0,
            $yearId,
        );

        $allCenterIds = array_merge([$centerId], $adosadoCenterIds);
        $excludeMeasurementUnitRows = ! $indicator->indicatorType?->usesInternalUnitMeasurement();

        if (! $excludeMeasurementUnitRows && $measurementUnitId === null) {
            return [];
        }

        $records = $this->monthlyRecordsByFieldId(
            $indicator,
            $fields,
            $allCenterIds,
            $yearId,
            $cenHieDependencyId,
            $measurementUnitId,
            $excludeMeasurementUnitRows,
        );

        $variables = [];

        foreach ($fields as $field) {
            $fieldRecords = $records->get($field->id, collect());
            $inputType = $field->input_type;
            $aggregationType = $field->aggregation_type;

            if ($inputType === DataFieldInputType::LineaBase) {
                $baselines = $fieldRecords
                    ->where('mes', null)
                    ->filter(fn ($record) => $record->valor !== null);

                $variables[$field->variable_key] = $baselines->isNotEmpty()
                    ? (float) $baselines->sum('valor')
                    : null;

                continue;
            }

            $periodRecords = $fieldRecords->whereIn('mes', $months);
            $hasAnyValue = $periodRecords->contains(fn ($record) => $record->valor !== null);

            if (! $hasAnyValue) {
                $variables[$field->variable_key] = null;

                continue;
            }

            if ($aggregationType !== null && ! $aggregationType->isAccumulative()) {
                $lastMonth = $periodRecords
                    ->filter(fn ($record) => $record->valor !== null)
                    ->max('mes');

                $variables[$field->variable_key] = (float) $periodRecords
                    ->where('mes', $lastMonth)
                    ->sum('valor');
            } else {
                $variables[$field->variable_key] = (float) $periodRecords->sum('valor');
            }
        }

        return $variables;
    }

    /**
     * @param  Collection<int, MetDataField>  $fields
     * @param  array<int, int>  $allCenterIds
     * @return Collection<int|string, Collection<int, object>>
     */
    private function monthlyRecordsByFieldId(
        MetIndicator $indicator,
        Collection $fields,
        array $allCenterIds,
        int $yearId,
        ?int $cenHieDependencyId,
        ?int $measurementUnitId,
        bool $excludeMeasurementUnitRows,
    ): Collection {
        $fieldIds = $fields->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $centerFlip = array_flip(array_map('intval', $allCenterIds));
        $fieldFlip = array_flip($fieldIds);

        if (
            $cenHieDependencyId !== null
            && $this->lastLoadedMonthService->monthlyPoolCoversIndicator(
                (int) $indicator->id,
                $cenHieDependencyId,
                $yearId,
                $excludeMeasurementUnitRows,
            )
        ) {
            $pooled = $this->lastLoadedMonthService->pooledMonthlyRowsForIndicators(
                [(int) $indicator->id],
                $cenHieDependencyId,
                $yearId,
                $excludeMeasurementUnitRows,
            );

            if ($pooled !== null) {
                return $pooled
                    ->filter(function ($row) use ($centerFlip, $fieldFlip, $measurementUnitId, $excludeMeasurementUnitRows): bool {
                        if (! isset($centerFlip[(int) $row->cen_center_id], $fieldFlip[(int) $row->met_data_field_id])) {
                            return false;
                        }

                        if ($excludeMeasurementUnitRows) {
                            return $row->met_measurement_unit_id === null;
                        }

                        return (int) $row->met_measurement_unit_id === (int) $measurementUnitId;
                    })
                    ->groupBy(fn ($row): int => (int) $row->met_data_field_id);
            }
        }

        $query = MetMonthlyData::query()
            ->whereIn('cen_center_id', $allCenterIds)
            ->where('year_id', $yearId)
            ->whereIn('met_data_field_id', $fieldIds);

        if ($cenHieDependencyId !== null) {
            $query->where('cen_hie_dependency_id', $cenHieDependencyId);
        }

        if ($excludeMeasurementUnitRows) {
            $query->whereNull('met_measurement_unit_id');
        } else {
            $query->where('met_measurement_unit_id', $measurementUnitId);
        }

        return $query->get()->groupBy('met_data_field_id');
    }

    /**
     * @return array{numerador: float|null, denominador: float|null}
     */
    public function resolvePrimaryComponenteValues(MetIndicator $indicator, array $variables): array
    {
        $indicator->loadMissing('dataFields');
        $fields = $indicator->dataFields;

        return [
            'numerador' => $this->resolveSingleComponenteValue($fields, $variables, DataFieldComponente::Numerador),
            'denominador' => $this->resolveSingleComponenteValue($fields, $variables, DataFieldComponente::Denominador),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function resolvePeriods(MetIndicator $indicator, int $ssId, int $yearId): array
    {
        $periodicidad = $this->periodicityResolver->resolveForCarga($indicator, $ssId, $yearId);
        $periodos = MetIndicator::PERIODOS[$periodicidad->value] ?? [];

        return array_keys($periodos);
    }

    /**
     * @return array<int, int>
     */
    private function resolveMonths(MetIndicator $indicator, string $periodo, int $ssId, int $yearId): array
    {
        $periodicidad = $this->periodicityResolver->resolveForCarga($indicator, $ssId, $yearId);
        $periodos = MetIndicator::PERIODOS[$periodicidad->value] ?? [];

        return $periodos[$periodo] ?? [];
    }

    private function resolveSingleComponenteValue(Collection $fields, array $variables, DataFieldComponente $componente): ?float
    {
        $matchingFields = $fields->where('componente', $componente)->values();

        if ($matchingFields->count() !== 1) {
            return null;
        }

        $variableKey = $matchingFields->first()->variable_key;

        return array_key_exists($variableKey, $variables) && $variables[$variableKey] !== null
            ? (float) $variables[$variableKey]
            : null;
    }
}
