<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\Metas\DataFieldAggregationType;
use App\Enums\Metas\DataFieldInputType;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\MetDataField;
use App\Models\MetFieldSsConfig;
use App\Models\MetIndicator;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetFieldSsConfigService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class IndicatorFieldSsConfigComponent extends Component
{
    use ResolvesInstrumentIndicatorTypeId;

    public int $indicatorId;

    public int $indicatorTypeId;

    /** @var array<int, array{input_type: string, aggregation_type: string|null}> */
    public array $fieldConfigs = [];

    public function mount(int $indicatorId, ?int $indicatorTypeId = null): void
    {
        if (! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId($indicatorTypeId);
        $this->indicatorId = $indicatorId;

        MetIndicator::query()
            ->where('id', $this->indicatorId)
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('year_id', $this->currentIndicatorYear()->id)
            ->firstOrFail();

        $this->loadFieldConfigs();
    }

    public function render(): View
    {
        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicatorId)
            ->orderBy('orden')
            ->get();

        $indicator = MetIndicator::query()
            ->with('dataFields')
            ->where('id', $this->indicatorId)
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('year_id', $this->currentIndicatorYear()->id)
            ->firstOrFail();

        $ssId = (int) $this->authUser()->cen_hie_dependency_id;
        $yearId = $this->currentIndicatorYear()->id;
        $fieldSsConfigService = app(MetFieldSsConfigService::class);

        $fieldSsConfigStatus = $fieldSsConfigService->configurationStatusForIndicator(
            $indicator,
            $ssId,
            $yearId,
        );

        $fieldHasCapturedData = $fieldSsConfigService->capturedDataByField($fields, $ssId, $yearId);
        $dataFieldInputTypeOptions = DataFieldInputType::options();
        $dataFieldAggregationTypeOptions = DataFieldAggregationType::options();

        return view('livewire.metas-instrument.indicator-field-ss-config', compact(
            'fields',
            'fieldSsConfigStatus',
            'fieldHasCapturedData',
            'dataFieldInputTypeOptions',
            'dataFieldAggregationTypeOptions',
        ));
    }

    public function saveConfiguration(): void
    {
        if (! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
            abort(403);
        }

        $fieldIds = MetDataField::query()
            ->where('met_indicator_id', $this->indicatorId)
            ->pluck('id')
            ->all();

        $rules = [];

        foreach ($fieldIds as $fieldId) {
            $rules["fieldConfigs.{$fieldId}.input_type"] = ['required', Rule::enum(DataFieldInputType::class)];
            $rules["fieldConfigs.{$fieldId}.aggregation_type"] = [
                'nullable',
                Rule::requiredIf(fn () => DataFieldInputType::tryFrom(
                    data_get($this->fieldConfigs, "{$fieldId}.input_type", '')
                )?->requiresAggregationType() ?? false),
                Rule::enum(DataFieldAggregationType::class),
            ];
        }

        $this->validate($rules);

        $ssId = (int) $this->authUser()->cen_hie_dependency_id;
        $yearId = $this->currentIndicatorYear()->id;
        $fieldSsConfigService = app(MetFieldSsConfigService::class);

        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicatorId)
            ->orderBy('orden')
            ->get()
            ->keyBy('id');

        $configs = MetFieldSsConfig::query()
            ->whereIn('met_data_field_id', $fieldIds)
            ->where('cen_hie_dependency_id', $ssId)
            ->where('year_id', $yearId)
            ->get()
            ->keyBy('met_data_field_id');

        $aggregationChangedWithCapturedData = [];

        foreach ($fieldIds as $fieldId) {
            /** @var MetDataField $field */
            $field = $fields->get($fieldId);

            if (! $field) {
                continue;
            }

            $config = $configs->get($fieldId);
            $previousInputType = $config?->input_type ?? $field->input_type;
            $newInputType = DataFieldInputType::from($this->fieldConfigs[$fieldId]['input_type']);

            if (
                $previousInputType !== $newInputType
                && $fieldSsConfigService->fieldHasCapturedData($fieldId, $ssId, $yearId)
            ) {
                $this->addError(
                    "fieldConfigs.{$fieldId}.input_type",
                    'No puede cambiar el tipo de entrada: ya hay datos cargados para esta variable en su Servicio de Salud y el año vigente.'
                );
            }

            if (! $newInputType->requiresAggregationType()) {
                continue;
            }

            $previousAggregationType = $config?->aggregation_type ?? $field->aggregation_type;
            $newAggregationType = DataFieldAggregationType::from(
                $this->fieldConfigs[$fieldId]['aggregation_type'] ?? DataFieldAggregationType::Sumatoria->value
            );

            if (
                $previousAggregationType !== $newAggregationType
                && $fieldSsConfigService->fieldHasCapturedData($fieldId, $ssId, $yearId)
            ) {
                $aggregationChangedWithCapturedData[] = $field->variable_key;
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        foreach ($fieldIds as $fieldId) {
            $inputType = $this->fieldConfigs[$fieldId]['input_type'];
            $inputTypeEnum = DataFieldInputType::tryFrom($inputType);
            $aggregationType = $inputTypeEnum?->requiresAggregationType() ?? false
                ? ($this->fieldConfigs[$fieldId]['aggregation_type'] ?? DataFieldAggregationType::Sumatoria->value)
                : null;

            MetFieldSsConfig::query()->updateOrCreate(
                [
                    'met_data_field_id' => $fieldId,
                    'cen_hie_dependency_id' => $ssId,
                    'year_id' => $yearId,
                ],
                [
                    'input_type' => $inputType,
                    'aggregation_type' => $aggregationType,
                ]
            );
        }

        $fieldSsConfigService->forgetConfigs($ssId, $yearId);

        session()->flash('field_ss_config_message', 'Configuración de tipos guardada correctamente.');

        if ($aggregationChangedWithCapturedData !== []) {
            session()->flash(
                'field_ss_config_warning',
                'El tipo de agregación cambió en variables con datos ya cargados en su Servicio de Salud ('.implode(', ', $aggregationChangedWithCapturedData).'). Revise el impacto en cálculos, resúmenes y avances antes de publicar resultados.'
            );
        }

        $this->loadFieldConfigs();
    }

    private function loadFieldConfigs(): void
    {
        $ssId = (int) $this->authUser()->cen_hie_dependency_id;
        $yearId = $this->currentIndicatorYear()->id;

        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicatorId)
            ->orderBy('orden')
            ->get();

        $configs = MetFieldSsConfig::query()
            ->whereIn('met_data_field_id', $fields->pluck('id'))
            ->where('cen_hie_dependency_id', $ssId)
            ->where('year_id', $yearId)
            ->get()
            ->keyBy('met_data_field_id');

        $this->fieldConfigs = [];

        foreach ($fields as $field) {
            $config = $configs->get($field->id);
            $this->fieldConfigs[$field->id] = [
                'input_type' => $config?->input_type?->value ?? $field->input_type->value,
                'aggregation_type' => $config?->aggregation_type?->value ?? $field->aggregation_type?->value,
            ];
        }
    }

    private function authUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function currentIndicatorYear(): Year
    {
        $year = session()->get('indicator_year');
        abort_unless($year instanceof Year, 500);

        return $year;
    }
}
