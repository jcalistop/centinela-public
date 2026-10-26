<?php

namespace App\Services\Metas;

use App\Enums\Metas\DataFieldAggregationType;
use App\Enums\Metas\DataFieldInputType;
use App\Enums\Metas\DataFieldSource;
use App\Models\MetDataField;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetMonthlyData;
use App\Models\Year;
use App\Services\Indicators\IndicatorAmbitoAssignmentService;
use App\Support\Metas\MetFormulaTokenPresenter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MetIndicatorCatalogPersistenceService
{
    /**
     * @param  array<string, mixed>  $indicatorAttributes
     * @param  array<int, array{id: int|null, label: string, componente: string, input_type: string, aggregation_type: string|null, fuente: string|null}>  $fields
     * @param  list<int>  $ambitoIds
     * @return array{indicator_id: int, formula_description: string}
     */
    public function persist(
        array $indicatorAttributes,
        array $fields,
        string $formulaJson,
        ?int $editingId,
        int $indicatorTypeId,
        Year $year,
        array $ambitoIds = [],
    ): array {
        $this->validateFields($fields);
        $fieldKeys = array_map(
            fn (int $index): string => $this->indexToVariableKey($index),
            array_keys($fields),
        );
        $this->validateFormula($formulaJson, $fieldKeys);

        if ($editingId) {
            MetIndicator::query()->whereKey($editingId)->update($indicatorAttributes);
            $indicatorId = $editingId;
        } else {
            $indicator = MetIndicator::query()->create($indicatorAttributes + [
                'indicator_type_id' => $indicatorTypeId,
                'year_id' => $year->id,
                'orden' => $this->nextOrdenForNewIndicator($indicatorTypeId, $year->id),
            ]);
            $indicatorId = $indicator->id;
        }

        $formulaDescription = $this->saveFieldsAndFormula($indicatorId, $fields, $formulaJson);

        $indicator = MetIndicator::query()->findOrFail($indicatorId);
        app(IndicatorAmbitoAssignmentService::class)->sync($indicator, $ambitoIds);

        return [
            'indicator_id' => $indicatorId,
            'formula_description' => $formulaDescription,
        ];
    }

    public function deleteScoped(MetIndicator $indicator, int $indicatorTypeId, int $yearId): void
    {
        if (
            (int) $indicator->indicator_type_id !== $indicatorTypeId
            || (int) $indicator->year_id !== $yearId
        ) {
            abort(404);
        }

        $deletionService = app(MetIndicatorLoadedDataDeletionService::class);

        if ($deletionService->hasLoadedDataInAnyDependency($indicator, $yearId)) {
            throw ValidationException::withMessages([
                'indicator' => 'No se puede eliminar: existen datos cargados para este indicador.',
            ]);
        }

        $indicator->delete();
    }

    public function nextOrdenForNewIndicator(int $indicatorTypeId, int $yearId): int
    {
        $max = MetIndicator::query()
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('year_id', $yearId)
            ->max('orden');

        return ((int) $max) + 1;
    }

    /**
     * @param  array<int, array{id: int|null, label: string, componente: string, input_type: string, aggregation_type: string|null, fuente: string|null}>  $fields
     */
    private function validateFields(array $fields): void
    {
        $errors = [];

        foreach ($fields as $i => $field) {
            if (empty(trim($field['label']))) {
                $errors["fields.{$i}.label"] = 'El label del campo es obligatorio.';
            }

            $fuente = $field['fuente'] ?? null;

            if ($fuente !== null && $fuente !== '' && ! in_array($fuente, $this->validDataFieldSourceValues(), true)) {
                $errors["fields.{$i}.fuente"] = 'La fuente seleccionada no es válida.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<string>  $fieldKeys
     */
    private function validateFormula(string $formulaJson, array $fieldKeys): void
    {
        $tokens = json_decode($formulaJson, true) ?? [];

        if ($tokens === []) {
            return;
        }

        $formulaError = MetFormulaTokenPresenter::validate($tokens, $fieldKeys);

        if ($formulaError !== null) {
            throw ValidationException::withMessages(['formulaJson' => $formulaError]);
        }
    }

    /**
     * @param  array<int, array{id: int|null, label: string, componente: string, input_type: string, aggregation_type: string|null, fuente: string|null}>  $fields
     */
    private function saveFieldsAndFormula(int $indicatorId, array $fields, string $formulaJson): string
    {
        $existingIds = array_values(array_filter(array_column($fields, 'id')));

        MetDataField::query()
            ->where('met_indicator_id', $indicatorId)
            ->when($existingIds !== [], fn ($q) => $q->whereNotIn('id', $existingIds))
            ->delete();

        foreach ($fields as $index => $fieldData) {
            $variableKey = $this->indexToVariableKey($index);

            $aggregationType = $this->resolveAggregationTypeValue(
                $fieldData['input_type'],
                $fieldData['aggregation_type'] ?? null,
            );

            $fuente = isset($fieldData['fuente']) && $fieldData['fuente'] !== '' && $fieldData['fuente'] !== null
                ? $fieldData['fuente']
                : null;

            if ($fieldData['id']) {
                MetDataField::query()->whereKey($fieldData['id'])->update([
                    'label' => $fieldData['label'],
                    'componente' => $fieldData['componente'],
                    'input_type' => $fieldData['input_type'],
                    'aggregation_type' => $aggregationType,
                    'fuente' => $fuente,
                    'variable_key' => $variableKey,
                    'orden' => $index,
                ]);
            } else {
                MetDataField::query()->create([
                    'met_indicator_id' => $indicatorId,
                    'label' => $fieldData['label'],
                    'componente' => $fieldData['componente'],
                    'input_type' => $fieldData['input_type'],
                    'aggregation_type' => $aggregationType,
                    'fuente' => $fuente,
                    'variable_key' => $variableKey,
                    'orden' => $index,
                ]);
            }
        }

        $tokens = json_decode($formulaJson, true) ?? [];

        if ($tokens !== []) {
            $description = MetFormulaTokenPresenter::describe($tokens);

            MetFormula::query()->updateOrCreate(
                ['met_indicator_id' => $indicatorId],
                ['expression' => $tokens, 'description' => $description]
            );

            return $description;
        }

        MetFormula::query()->where('met_indicator_id', $indicatorId)->delete();

        return '';
    }

    public function indexToVariableKey(int $index): string
    {
        if ($index < 26) {
            return chr(65 + $index);
        }

        $q = intdiv($index - 26, 26);
        $r = ($index - 26) % 26;

        return chr(65 + $q).chr(65 + $r);
    }

    public function resolveAggregationTypeValue(string $inputTypeValue, ?string $aggregationTypeValue): ?string
    {
        $inputType = DataFieldInputType::tryFrom($inputTypeValue);

        if ($inputType === null || ! $inputType->requiresAggregationType()) {
            return null;
        }

        return $aggregationTypeValue ?? DataFieldAggregationType::Sumatoria->value;
    }

    /**
     * @return list<string>
     */
    public function validDataFieldSourceValues(): array
    {
        return array_map(
            fn (DataFieldSource $source) => $source->value,
            DataFieldSource::cases()
        );
    }

    /**
     * @return array<int, array{id: int|null, label: string, componente: string, input_type: string, aggregation_type: string|null, fuente: string|null}>
     */
    public function loadFieldsFromDatabase(int $indicatorId): array
    {
        return MetDataField::query()
            ->where('met_indicator_id', $indicatorId)
            ->orderBy('orden')
            ->get()
            ->map(fn (MetDataField $f) => [
                'id' => $f->id,
                'label' => $f->label,
                'componente' => $f->componente->value,
                'input_type' => $f->input_type->value,
                'aggregation_type' => $f->aggregation_type?->value,
                'fuente' => $f->fuente,
            ])
            ->values()
            ->all();
    }

    public function loadFormulaJson(int $indicatorId): string
    {
        $formula = MetFormula::query()->where('met_indicator_id', $indicatorId)->first();

        return $formula ? json_encode($formula->expression) : '[]';
    }

    public function loadFormulaDescription(int $indicatorId): string
    {
        return MetFormula::query()->where('met_indicator_id', $indicatorId)->value('description') ?? '';
    }

    public function fieldHasMonthlyData(int $fieldId): bool
    {
        if (! Schema::hasTable('met_monthly_data')) {
            return false;
        }

        return MetMonthlyData::query()
            ->withoutGlobalScope('belongs_to_health_service')
            ->where('met_data_field_id', $fieldId)
            ->exists();
    }
}
