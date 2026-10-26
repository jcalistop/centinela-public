<?php

namespace App\Services\Metas;

use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetMonthlyData;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MetPeriodCaptureXlsxNormalizer
{
    public function __construct(
        private readonly MetPeriodCaptureCompletenessValidator $completenessValidator,
        private readonly MetFieldSsConfigService $fieldSsConfigService,
    ) {}

    /**
     * @param  Collection<int, MetDataField>  $effectiveFields
     * @param  array<int, float|null>  $valuesByFieldId  Valores leídos del archivo (null = celda vacía).
     * @return array<int, float> Valores normalizados listos para persistir.
     */
    public function normalizeImportedPeriodValues(Collection $effectiveFields, array $valuesByFieldId): array
    {
        $hasAnyValue = false;

        foreach ($valuesByFieldId as $value) {
            if (! $this->completenessValidator->valueIsEmpty($value)) {
                $hasAnyValue = true;

                break;
            }
        }

        if (! $hasAnyValue) {
            return [];
        }

        $normalized = [];

        foreach ($effectiveFields as $field) {
            $raw = $valuesByFieldId[$field->id] ?? null;
            $normalized[$field->id] = $this->completenessValidator->valueIsEmpty($raw) ? 0.0 : (float) $raw;
        }

        return $normalized;
    }

    /**
     * Tras importar al menos un valor en un período, rellena con 0 los campos aplicables faltantes en BD.
     *
     * @param  array<int, string>  $importErrors
     * @param  array<string, array{centerId: int, measurementUnitId: ?int}>  $affectedScopes
     */
    public function zeroFillMissingFieldsInDatabase(
        MetIndicator $indicator,
        int $centerId,
        int $mes,
        ?int $measurementUnitId,
        int $ssId,
        int $yearId,
        array &$importErrors,
        int &$processedCount,
        array &$affectedScopes,
        string $sheetName,
        int $rowNumber,
    ): void {
        $effectiveFields = $this->fieldSsConfigService->applyEffectiveTypes(
            $indicator->dataFields,
            $ssId,
            $yearId,
        )->filter(
            fn (MetDataField $field): bool => $this->completenessValidator->fieldAppliesToMonth(
                $field,
                $mes,
                $ssId,
                $yearId,
            ),
        );

        if ($effectiveFields->isEmpty()) {
            return;
        }

        $existing = MetMonthlyData::query()
            ->where('cen_hie_dependency_id', $ssId)
            ->where('year_id', $yearId)
            ->where('cen_center_id', $centerId)
            ->where('mes', $mes)
            ->whereIn('met_data_field_id', $effectiveFields->pluck('id'))
            ->when(
                $measurementUnitId !== null,
                fn ($query) => $query->where('met_measurement_unit_id', $measurementUnitId),
                fn ($query) => $query->whereNull('met_measurement_unit_id'),
            )
            ->get()
            ->keyBy('met_data_field_id');

        $valuesByFieldId = [];
        foreach ($effectiveFields as $field) {
            $valuesByFieldId[$field->id] = $existing->get($field->id)?->valor;
        }

        if (! $this->completenessValidator->isPeriodIncomplete($valuesByFieldId, $effectiveFields)) {
            return;
        }

        foreach ($effectiveFields as $field) {
            $record = $existing->get($field->id);
            if ($record !== null && $record->valor !== null) {
                continue;
            }

            try {
                MetMonthlyData::updateOrCreate(
                    [
                        'met_data_field_id' => $field->id,
                        'cen_center_id' => $centerId,
                        'cen_hie_dependency_id' => $ssId,
                        'year_id' => $yearId,
                        'mes' => $mes,
                        'met_measurement_unit_id' => $measurementUnitId,
                    ],
                    ['valor' => 0],
                );
                $processedCount++;

                $scopeKey = $centerId.':'.(int) ($measurementUnitId ?? 0);
                $affectedScopes[$scopeKey] = [
                    'centerId' => $centerId,
                    'measurementUnitId' => $measurementUnitId,
                ];
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();
                $importErrors[] = "[{$sheetName} fila {$rowNumber}] {$message}";
            }
        }
    }
}
