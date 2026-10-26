<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\Metas\DataFieldAggregationType;
use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\DataFieldInputType;
use App\Models\MetDataField;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetMonthlyData;
use App\Models\User;
use App\Support\Metas\MetFormulaTokenPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;

class DataFieldsComponent extends Component
{
    public int $indicatorId;

    /** @var array<int, array{id: int|null, label: string, componente: string, input_type: string, aggregation_type: string|null}> */
    public array $fields = [];

    public string $formulaJson = '[]';

    public string $formulaDescription = '';

    // Form for adding a new field
    public string $newFieldLabel = '';

    public string $newFieldComponente = DataFieldComponente::Numerador->value;

    public string $newFieldInputType = DataFieldInputType::Mensual->value;

    public string $newFieldAggregationType = DataFieldAggregationType::Sumatoria->value;

    public function mount(int $indicatorId): void
    {
        if (! $this->authUser()->hasRole(['parametrizador', 'admin'])) {
            abort(403);
        }

        $this->indicatorId = $indicatorId;
        $this->loadFromDatabase();
    }

    public function render(): View
    {
        $indicator = MetIndicator::findOrFail($this->indicatorId);
        $dataFieldInputTypeOptions = DataFieldInputType::options();
        $dataFieldAggregationTypeOptions = DataFieldAggregationType::options();

        return view('livewire.metas-instrument.data-fields-component', compact(
            'indicator',
            'dataFieldInputTypeOptions',
            'dataFieldAggregationTypeOptions',
        ));
    }

    public function addField(): void
    {
        $this->validate([
            'newFieldLabel' => ['required', 'string', 'max:255'],
            'newFieldComponente' => ['required', Rule::enum(DataFieldComponente::class)],
            'newFieldInputType' => ['required', Rule::enum(DataFieldInputType::class)],
            'newFieldAggregationType' => [
                Rule::requiredIf(fn () => DataFieldInputType::tryFrom($this->newFieldInputType)?->requiresAggregationType() ?? false),
                'nullable',
                Rule::enum(DataFieldAggregationType::class),
            ],
        ]);

        if (count($this->fields) >= 26) {
            $this->addError('newFieldLabel', 'Se alcanzó el límite de 26 campos por indicador.');

            return;
        }

        $this->fields[] = [
            'id' => null,
            'label' => $this->newFieldLabel,
            'componente' => $this->newFieldComponente,
            'input_type' => $this->newFieldInputType,
            'aggregation_type' => $this->resolveAggregationTypeValue($this->newFieldInputType, $this->newFieldAggregationType),
        ];

        $this->newFieldLabel = '';
        $this->newFieldComponente = DataFieldComponente::Numerador->value;
        $this->newFieldInputType = DataFieldInputType::Mensual->value;
        $this->newFieldAggregationType = DataFieldAggregationType::Sumatoria->value;
    }

    public function removeField(int $index): void
    {
        $field = $this->fields[$index] ?? null;

        if ($field === null) {
            return;
        }

        if ($field['id'] && Schema::hasTable('met_monthly_data')) {
            $hasData = MetMonthlyData::where('met_data_field_id', $field['id'])->exists();

            if ($hasData) {
                $this->addError('fields', 'No se puede eliminar un campo con datos de carga existentes.');

                return;
            }
        }

        // Warn if current formula tokens reference this variable_key
        $variableKey = $this->indexToVariableKey($index);
        $tokens = json_decode($this->formulaJson, true) ?? [];
        $referenced = MetFormulaTokenPresenter::referencesVariable($tokens, $variableKey);

        if ($referenced) {
            session()->flash('fields_warning', "La variable {$variableKey} está referenciada en la fórmula. Al eliminar el campo, la fórmula quedará inválida.");
        }

        array_splice($this->fields, $index, 1);
    }

    public function moveUp(int $index): void
    {
        if ($index <= 0 || $index >= count($this->fields)) {
            return;
        }

        [$this->fields[$index - 1], $this->fields[$index]] = [$this->fields[$index], $this->fields[$index - 1]];
    }

    public function moveDown(int $index): void
    {
        if ($index < 0 || $index >= count($this->fields) - 1) {
            return;
        }

        [$this->fields[$index + 1], $this->fields[$index]] = [$this->fields[$index], $this->fields[$index + 1]];
    }

    public function saveAll(): void
    {
        foreach ($this->fields as $i => $field) {
            if (empty(trim($field['label']))) {
                $this->addError("fields.{$i}.label", 'El label del campo es obligatorio.');

                return;
            }
        }

        // Delete fields not present in the current list
        $existingIds = array_values(array_filter(array_column($this->fields, 'id')));

        MetDataField::where('met_indicator_id', $this->indicatorId)
            ->when(! empty($existingIds), fn ($q) => $q->whereNotIn('id', $existingIds))
            ->delete();

        // Update or create each field
        foreach ($this->fields as $index => $fieldData) {
            $variableKey = $this->indexToVariableKey($index);

            $aggregationType = $this->resolveAggregationTypeValue(
                $fieldData['input_type'],
                $fieldData['aggregation_type'] ?? null,
            );

            if ($fieldData['id']) {
                MetDataField::where('id', $fieldData['id'])->update([
                    'label' => $fieldData['label'],
                    'componente' => $fieldData['componente'],
                    'input_type' => $fieldData['input_type'],
                    'aggregation_type' => $aggregationType,
                    'variable_key' => $variableKey,
                    'orden' => $index,
                ]);
            } else {
                $new = MetDataField::create([
                    'met_indicator_id' => $this->indicatorId,
                    'label' => $fieldData['label'],
                    'componente' => $fieldData['componente'],
                    'input_type' => $fieldData['input_type'],
                    'aggregation_type' => $aggregationType,
                    'variable_key' => $variableKey,
                    'orden' => $index,
                ]);

                $this->fields[$index]['id'] = $new->id;
            }
        }

        // Save formula
        $tokens = json_decode($this->formulaJson, true) ?? [];

        if (! empty($tokens)) {
            $fieldKeys = array_map(
                fn (int $index): string => $this->indexToVariableKey($index),
                array_keys($this->fields),
            );
            $formulaError = MetFormulaTokenPresenter::validate($tokens, $fieldKeys);

            if ($formulaError !== null) {
                $this->addError('formulaJson', $formulaError);

                return;
            }

            $description = $this->buildFormulaDescription($tokens);

            MetFormula::updateOrCreate(
                ['met_indicator_id' => $this->indicatorId],
                ['expression' => $tokens, 'description' => $description]
            );

            $this->formulaDescription = $description;
        } else {
            MetFormula::where('met_indicator_id', $this->indicatorId)->delete();
            $this->formulaDescription = '';
        }

        $this->loadFromDatabase();
        session()->flash('fields_message', 'Maqueta guardada correctamente.');
    }

    private function loadFromDatabase(): void
    {
        $dbFields = MetDataField::where('met_indicator_id', $this->indicatorId)
            ->orderBy('orden')
            ->get();

        $this->fields = $dbFields->map(fn ($f) => [
            'id' => $f->id,
            'label' => $f->label,
            'componente' => $f->componente->value,
            'input_type' => $f->input_type->value,
            'aggregation_type' => $f->aggregation_type?->value,
        ])->toArray();

        $formula = MetFormula::where('met_indicator_id', $this->indicatorId)->first();
        $this->formulaJson = $formula ? json_encode($formula->expression) : '[]';
        $this->formulaDescription = $formula?->description ?? '';
    }

    private function indexToVariableKey(int $index): string
    {
        if ($index < 26) {
            return chr(65 + $index);
        }

        $q = intdiv($index - 26, 26);
        $r = ($index - 26) % 26;

        return chr(65 + $q).chr(65 + $r);
    }

    private function buildFormulaDescription(array $tokens): string
    {
        return MetFormulaTokenPresenter::describe($tokens);
    }

    private function resolveAggregationTypeValue(string $inputTypeValue, ?string $aggregationTypeValue): ?string
    {
        $inputType = DataFieldInputType::tryFrom($inputTypeValue);

        if ($inputType === null || ! $inputType->requiresAggregationType()) {
            return null;
        }

        return $aggregationTypeValue ?? DataFieldAggregationType::Sumatoria->value;
    }

    private function authUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
