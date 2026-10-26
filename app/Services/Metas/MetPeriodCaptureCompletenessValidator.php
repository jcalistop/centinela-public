<?php

namespace App\Services\Metas;

use App\Enums\Metas\DataFieldInputType;
use App\Models\MetDataField;
use Illuminate\Support\Collection;

class MetPeriodCaptureCompletenessValidator
{
    public function valueIsEmpty(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    public function fieldAppliesToMonth(MetDataField $field, int $mes, int $ssId, int $yearId): bool
    {
        // Tras applyEffectiveTypes el campo ya trae input_type efectivo (SDD-172);
        // resolveInputType no reconsulta si está marcado o hay memo request-scoped.
        $inputType = $field->resolveInputType($ssId, $yearId);

        if ($inputType === DataFieldInputType::LineaBase) {
            return false;
        }

        return in_array($mes, $inputType->monthNumbers(), true);
    }

    /**
     * @param  Collection<int, MetDataField>  $fields
     * @return Collection<int, MetDataField>
     */
    public function applicableFieldsForMonth(Collection $fields, int $mes, int $ssId, int $yearId): Collection
    {
        return $fields->filter(
            fn (MetDataField $field): bool => $this->fieldAppliesToMonth($field, $mes, $ssId, $yearId),
        )->values();
    }

    /**
     * @param  array<int, mixed>  $valuesByFieldId
     * @param  Collection<int, MetDataField>  $applicableFields
     */
    public function isPeriodIncomplete(array $valuesByFieldId, Collection $applicableFields): bool
    {
        if ($applicableFields->isEmpty()) {
            return false;
        }

        $hasFilled = false;
        $hasEmpty = false;

        foreach ($applicableFields as $field) {
            if ($this->valueIsEmpty($valuesByFieldId[$field->id] ?? null)) {
                $hasEmpty = true;
            } else {
                $hasFilled = true;
            }
        }

        return $hasFilled && $hasEmpty;
    }

    /**
     * @param  array<int, mixed>  $combinedValuesByFieldId
     * @param  Collection<int, MetDataField>  $applicableFields
     */
    public function validateManualPeriod(array $combinedValuesByFieldId, Collection $applicableFields): ?string
    {
        if (! $this->isPeriodIncomplete($combinedValuesByFieldId, $applicableFields)) {
            return null;
        }

        return 'Período incompleto: complete todos los campos aplicables del mes o vacíe el período entero.';
    }
}
