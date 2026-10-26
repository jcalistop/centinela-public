<?php

namespace App\Services\Metas;

use App\Enums\Metas\DataFieldAggregationType;
use App\Enums\Metas\DataFieldInputType;
use App\Models\MetDataField;
use App\Models\MetFieldSsConfig;
use App\Models\MetIndicator;
use App\Models\MetMonthlyData;
use Illuminate\Support\Collection;

class MetFieldSsConfigService
{
    public const STATUS_NO_FIELDS = 'no_fields';

    public const STATUS_NONE = 'none';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_COMPLETE = 'complete';

    /**
     * Memo request-scoped: "{ssId}:{yearId}" → map fieldId → config|null (null = sin fila).
     *
     * @var array<string, array<int, MetFieldSsConfig|null>>
     */
    private array $configMemoBySsYear = [];

    /**
     * @return Collection<int, MetFieldSsConfig>
     */
    public function configsForIndicator(MetIndicator $indicator, int $ssId, int $yearId): Collection
    {
        $fieldIds = $indicator->relationLoaded('dataFields')
            ? $indicator->dataFields->pluck('id')
            : $indicator->dataFields()->pluck('id');

        if ($fieldIds->isEmpty()) {
            return collect();
        }

        $this->preloadConfigs($fieldIds->map(fn ($id): int => (int) $id)->all(), $ssId, $yearId);

        $map = $this->memoMap($ssId, $yearId);

        return $fieldIds
            ->map(fn ($id): ?MetFieldSsConfig => $map[(int) $id] ?? null)
            ->filter(fn (?MetFieldSsConfig $config): bool => $config !== null)
            ->values();
    }

    public function configurationStatusForIndicator(MetIndicator $indicator, int $ssId, int $yearId): string
    {
        $fields = $indicator->relationLoaded('dataFields')
            ? $indicator->dataFields
            : $indicator->dataFields()->orderBy('orden')->get();

        if ($fields->isEmpty()) {
            return self::STATUS_NO_FIELDS;
        }

        $configs = $this->configsForIndicator($indicator, $ssId, $yearId)->keyBy('met_data_field_id');

        return $this->resolveConfigurationStatus($fields, $configs);
    }

    /**
     * @param  Collection<int, MetIndicator>  $indicators
     * @return Collection<int, string>
     */
    public function configurationStatusByIndicators(Collection $indicators, int $ssId, int $yearId): Collection
    {
        if ($indicators->isEmpty()) {
            return collect();
        }

        $fieldIds = $indicators
            ->flatMap(fn (MetIndicator $indicator) => $indicator->relationLoaded('dataFields')
                ? $indicator->dataFields
                : collect())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->preloadConfigs($fieldIds, $ssId, $yearId);

        $configsByFieldId = collect($this->memoMap($ssId, $yearId))
            ->filter(fn (?MetFieldSsConfig $config): bool => $config !== null);

        return $indicators->mapWithKeys(function (MetIndicator $indicator) use ($configsByFieldId): array {
            $fields = $indicator->relationLoaded('dataFields')
                ? $indicator->dataFields
                : collect();

            if ($fields->isEmpty()) {
                return [$indicator->id => self::STATUS_NO_FIELDS];
            }

            return [
                $indicator->id => $this->resolveConfigurationStatus($fields, $configsByFieldId),
            ];
        });
    }

    /**
     * @param  Collection<int, MetDataField>  $fields
     * @param  Collection<int, MetFieldSsConfig>  $configsByFieldId
     */
    private function resolveConfigurationStatus(Collection $fields, Collection $configsByFieldId): string
    {
        $completeCount = 0;

        foreach ($fields as $field) {
            $config = $configsByFieldId->get($field->id);

            if ($config instanceof MetFieldSsConfig && $this->isValidConfig($config)) {
                $completeCount++;
            }
        }

        if ($completeCount === 0) {
            return self::STATUS_NONE;
        }

        if ($completeCount === $fields->count()) {
            return self::STATUS_COMPLETE;
        }

        return self::STATUS_PARTIAL;
    }

    public function allowsReferenteDataEntry(MetIndicator $indicator, int $ssId, int $yearId): bool
    {
        $status = $this->configurationStatusForIndicator($indicator, $ssId, $yearId);

        return in_array($status, [self::STATUS_COMPLETE, self::STATUS_NO_FIELDS], true);
    }

    public function fieldHasCapturedData(int $fieldId, int $ssId, int $yearId): bool
    {
        return MetMonthlyData::query()
            ->where('met_data_field_id', $fieldId)
            ->where('cen_hie_dependency_id', $ssId)
            ->where('year_id', $yearId)
            ->whereNotNull('valor')
            ->exists();
    }

    /**
     * @param  Collection<int, MetDataField>  $fields
     * @return array<int, bool>
     */
    public function capturedDataByField(Collection $fields, int $ssId, int $yearId): array
    {
        if ($fields->isEmpty()) {
            return [];
        }

        $fieldIdsWithData = MetMonthlyData::query()
            ->whereIn('met_data_field_id', $fields->pluck('id'))
            ->where('cen_hie_dependency_id', $ssId)
            ->where('year_id', $yearId)
            ->whereNotNull('valor')
            ->distinct()
            ->pluck('met_data_field_id')
            ->all();

        $capturedDataByField = [];

        foreach ($fields as $field) {
            $capturedDataByField[$field->id] = in_array($field->id, $fieldIdsWithData, true);
        }

        return $capturedDataByField;
    }

    /**
     * @param  list<int>  $fieldIds
     */
    public function preloadConfigs(array $fieldIds, int $ssId, int $yearId): void
    {
        $fieldIds = array_values(array_unique(array_map('intval', $fieldIds)));
        if ($fieldIds === []) {
            return;
        }

        $key = $this->memoKey($ssId, $yearId);
        $map = $this->configMemoBySsYear[$key] ?? [];

        $missing = array_values(array_filter(
            $fieldIds,
            fn (int $fieldId): bool => ! array_key_exists($fieldId, $map),
        ));

        if ($missing === []) {
            return;
        }

        $loaded = MetFieldSsConfig::query()
            ->whereIn('met_data_field_id', $missing)
            ->where('cen_hie_dependency_id', $ssId)
            ->where('year_id', $yearId)
            ->get()
            ->keyBy('met_data_field_id');

        foreach ($missing as $fieldId) {
            $map[$fieldId] = $loaded->get($fieldId);
        }

        $this->configMemoBySsYear[$key] = $map;
    }

    public function configForField(int $fieldId, int $ssId, int $yearId): ?MetFieldSsConfig
    {
        $key = $this->memoKey($ssId, $yearId);
        $map = $this->configMemoBySsYear[$key] ?? [];

        if (! array_key_exists($fieldId, $map)) {
            $this->preloadConfigs([$fieldId], $ssId, $yearId);
            $map = $this->configMemoBySsYear[$key] ?? [];
        }

        return $map[$fieldId] ?? null;
    }

    public function forgetConfigs(?int $ssId = null, ?int $yearId = null): void
    {
        if ($ssId === null || $yearId === null) {
            $this->configMemoBySsYear = [];

            return;
        }

        unset($this->configMemoBySsYear[$this->memoKey($ssId, $yearId)]);
    }

    /**
     * @param  Collection<int, MetDataField>  $fields
     * @return Collection<int, MetDataField>
     */
    public function applyEffectiveTypes(Collection $fields, int $ssId, int $yearId): Collection
    {
        if ($fields->isEmpty()) {
            return $fields;
        }

        $fieldIds = $fields->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->preloadConfigs($fieldIds, $ssId, $yearId);
        $configs = $this->memoMap($ssId, $yearId);

        return $fields->map(function (MetDataField $field) use ($configs, $ssId, $yearId): MetDataField {
            $config = $configs[(int) $field->id] ?? null;

            if ($config !== null) {
                $field->setAttribute('input_type', $config->input_type);
                $field->setAttribute('aggregation_type', $config->aggregation_type);
            }

            $field->markSsEffectiveTypes($ssId, $yearId);

            return $field;
        });
    }

    public function isValidConfig(MetFieldSsConfig $config): bool
    {
        if (! in_array($config->input_type, [
            DataFieldInputType::Mensual,
            DataFieldInputType::Trimestral,
            DataFieldInputType::Semestral,
            DataFieldInputType::Anual,
            DataFieldInputType::LineaBase,
        ], true)) {
            return false;
        }

        if ($config->input_type === DataFieldInputType::LineaBase) {
            return $config->aggregation_type === null;
        }

        return in_array($config->aggregation_type, [
            DataFieldAggregationType::Sumatoria,
            DataFieldAggregationType::UltimoDato,
        ], true);
    }

    /**
     * Filas de solo lectura para UI de carga/ficha (tipos efectivos por SS y año).
     *
     * @return list<array{variable_key: string, label: string, input_type_label: string, aggregation_type_label: string}>
     */
    public function effectiveFieldTypeRowsForIndicator(MetIndicator $indicator, int $ssId, int $yearId): array
    {
        $fields = $indicator->relationLoaded('dataFields')
            ? $indicator->dataFields->sortBy('orden')->values()
            : $indicator->dataFields()->orderBy('orden')->get();

        if ($fields->isEmpty()) {
            return [];
        }

        $effectiveFields = $this->applyEffectiveTypes($fields, $ssId, $yearId);

        return $effectiveFields->map(function (MetDataField $field): array {
            $inputType = $field->input_type;
            $aggregationType = $field->aggregation_type;

            return [
                'variable_key' => $field->variable_key,
                'label' => $field->label,
                'input_type_label' => $inputType->label(),
                'aggregation_type_label' => $inputType->requiresAggregationType()
                    ? ($aggregationType?->label() ?? '—')
                    : '—',
            ];
        })->values()->all();
    }

    private function memoKey(int $ssId, int $yearId): string
    {
        return $ssId.':'.$yearId;
    }

    /**
     * @return array<int, MetFieldSsConfig|null>
     */
    private function memoMap(int $ssId, int $yearId): array
    {
        return $this->configMemoBySsYear[$this->memoKey($ssId, $yearId)] ?? [];
    }
}
