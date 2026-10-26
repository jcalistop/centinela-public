<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\DataFieldInputType;
use App\Enums\Metas\MetInstrumentPeriodo;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\CenCenter;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetIndicatorReferente;
use App\Models\MetMeasurementUnit;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetEstablishmentIndicatorLoadability;
use App\Services\Metas\MetEstablishmentResponsibleAccess;
use App\Services\Metas\MetFieldSsConfigService;
use App\Services\Metas\MetIndicatorCalculationErrorService;
use App\Services\Metas\MetIndicatorEvaluationService;
use App\Services\Metas\MetIndicatorIncompletePeriodService;
use App\Services\Metas\MetIndicatorPeriodicityResolver;
use App\Services\Metas\MetPeriodCaptureCompletenessValidator;
use App\Services\Metas\MetRecalculationDispatcher;
use App\Services\Metas\MetRecalculationScope;
use App\Services\Metas\MetStageAccessService;
use App\Services\MetDataAggregatorService;
use App\Support\Metas\MetMonthlyDataInputFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MonthlyDataEntryComponent extends Component
{
    use ResolvesInstrumentIndicatorTypeId;

    public int $indicatorId;

    public int $centerId;

    public ?int $measurementUnitId = null;

    /** @var array<int, string> */
    public array $months = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    /** @var array<int, array<int, string|null>> */
    public array $monthlyValues = [];

    /** @var array<int, array<int, string>> */
    public array $monthlyObservations = [];

    /** @var array<int, string|null> */
    public array $baselineValues = [];

    /** @var array<int, string> */
    public array $baselineObservations = [];

    public bool $canEdit = false;

    public bool $isAssigned = false;

    public string $etapaVigente = 'CERRADO';

    public string $preview = 'N/D';

    public string $accessMessage = '';

    public int $indicatorTypeId;

    /** @var array<string, string> */
    public array $persistedCalculationErrorsByPeriodo = [];

    /** @var array<int, bool> */
    public array $persistedIncompleteMonths = [];

    public function mount(int $indicatorId, int $centerId, ?int $indicatorTypeId = null, ?int $measurementUnitId = null): void
    {
        if (! $this->authUser()->hasRole(['referente', 'parametrizador', 'control-gestion', 'admin', 'visualizador'])) {
            abort(403);
        }

        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId($indicatorTypeId);

        $this->indicatorId = $indicatorId;
        $this->centerId = $centerId;
        $this->measurementUnitId = $measurementUnitId;

        $indicator = MetIndicator::query()
            ->where('id', $this->indicatorId)
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('year_id', $this->currentIndicatorYear()->id)
            ->firstOrFail();

        if ($this->authUser()->hasRole('referente')) {
            $indicator->load('dataFields');

            if (! app(MetFieldSsConfigService::class)->allowsReferenteDataEntry(
                $indicator,
                (int) $this->authUser()->cen_hie_dependency_id,
                $this->currentIndicatorYear()->id,
            )) {
                abort(403, 'Control de Gestión debe definir el tipo de entrada y el tipo de agregación para este indicador antes de cargar datos.');
            }
        }

        $instrumentType = IndicatorType::query()->findOrFail($this->indicatorTypeId);

        if ($instrumentType->usesInternalUnitMeasurement() && $this->measurementUnitId === null) {
            abort(404);
        }

        if (! $instrumentType->usesInternalUnitMeasurement()) {
            $this->measurementUnitId = null;
        }

        $center = CenCenter::query()
            ->where('id', $this->centerId)
            ->where('cen_hie_dependency_id', $this->authUser()->cen_hie_dependency_id)
            ->first();

        if (! $center) {
            abort(403);
        }

        if ($this->measurementUnitId !== null) {
            $unit = MetMeasurementUnit::query()
                ->where('id', $this->measurementUnitId)
                ->where('cen_center_id', $this->centerId)
                ->where('year_id', $this->currentIndicatorYear()->id)
                ->where('cen_hie_dependency_id', $this->authUser()->cen_hie_dependency_id)
                ->first();

            if (! $unit) {
                abort(404);
            }

            if (! $unit->activo && ! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
                abort(403, 'La unidad está inactiva.');
            }
        }

        if (! $instrumentType->usesInternalUnitMeasurement()) {
            $aplicaOk = app(MetEstablishmentIndicatorLoadability::class)->establishmentIndicatorAplicaForCenter(
                $this->indicatorId,
                $center,
                $this->currentIndicatorYear()->id,
                (int) $this->authUser()->cen_hie_dependency_id,
                $this->indicatorTypeId,
            );

            if (! $aplicaOk && ! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
                abort(403, 'Este indicador no aplica para este establecimiento.');
            }
        } else {
            $aplicaQuery = MetIndicatorConfiguration::query()
                ->where('met_indicator_id', $this->indicatorId)
                ->where('cen_center_id', $this->centerId)
                ->where('year_id', $this->currentIndicatorYear()->id);

            if ($this->measurementUnitId !== null) {
                $aplicaQuery->where('met_measurement_unit_id', $this->measurementUnitId);
            } else {
                $aplicaQuery->whereRaw('1 = 0');
            }

            $aplica = $aplicaQuery->value('aplica');

            if ($aplica !== null && ! $aplica && ! $this->authUser()->hasRole(['control-gestion', 'admin'])) {
                abort(403, 'Este indicador no aplica para este establecimiento.');
            }
        }

        $setting = IndicatorTypeSetting::getData($this->indicatorTypeId);
        $this->etapaVigente = $setting->etapa_vigente->value;

        $this->isAssigned = MetIndicatorReferente::query()
            ->where('met_indicator_id', $indicator->id)
            ->where('year_id', $this->currentIndicatorYear()->id)
            ->where('cen_hie_dependency_id', $this->authUser()->cen_hie_dependency_id)
            ->where('user_id', Auth::id())
            ->exists();

        $etapaPermiteCarga = app(MetStageAccessService::class)
            ->canWriteMonthlyData($setting->etapa_vigente);

        $user = $this->authUser();
        $responsibleAccess = app(MetEstablishmentResponsibleAccess::class);
        $yearId = $this->currentIndicatorYear()->id;

        if ($user->hasRole('referente') && $responsibleAccess->isDesignatedResponsible($user, $yearId, $this->indicatorTypeId)) {
            $centerModel = CenCenter::query()->findOrFail($this->centerId);
            $this->canEdit = $responsibleAccess->canLoadDataForCenter(
                $user,
                $indicator,
                $centerModel,
                $yearId,
                $instrumentType,
            );
        } else {
            $this->canEdit = ($user->role->name === 'referente' && $this->isAssigned && $etapaPermiteCarga)
                || ($user->hasRole(['control-gestion', 'admin']) && $etapaPermiteCarga);
        }

        if ($this->canEdit) {
            $this->accessMessage = '';
        } elseif ($this->authUser()->hasRole(['control-gestion', 'admin'])) {
            $this->accessMessage = 'Instrumento cerrado para carga en la etapa actual.';
        } elseif ($this->authUser()->role->name === 'referente') {
            if (! $this->isAssigned) {
                $this->accessMessage = 'No fue designado para cargar datos en este indicador.';
            } elseif (! $etapaPermiteCarga) {
                $this->accessMessage = 'Instrumento cerrado para carga en la etapa actual.';
            } else {
                $this->accessMessage = '';
            }
        } else {
            $this->accessMessage = 'Vista de solo lectura para su rol.';
        }

        $this->initializeInputArrays($this->getFields());
        $this->loadExistingData();
        $this->refreshPreview();
        $this->refreshPeriodRowAlerts();
    }

    public function render(): View
    {
        $indicator = MetIndicator::with(['dataFields', 'formula'])
            ->where('id', $this->indicatorId)
            ->where('year_id', $this->currentIndicatorYear()->id)
            ->firstOrFail();

        $fields = $this->getFields();
        $monthlyFields = $fields->where('input_type', DataFieldInputType::Mensual)->values();
        $trimestralFields = $fields->where('input_type', DataFieldInputType::Trimestral)->values();
        $semestralFields = $fields->where('input_type', DataFieldInputType::Semestral)->values();
        $anualFields = $fields->where('input_type', DataFieldInputType::Anual)->values();
        $baselineFields = $fields->where('input_type', DataFieldInputType::LineaBase)->values();

        $metaConfigQuery = MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $indicator->id)
            ->where('cen_center_id', $this->centerId)
            ->where('year_id', $this->currentIndicatorYear()->id);

        if ($this->measurementUnitId !== null) {
            $metaConfigQuery->where('met_measurement_unit_id', $this->measurementUnitId);
        } else {
            $metaConfigQuery->whereNull('met_measurement_unit_id');
        }

        $metaConfig = $metaConfigQuery->first();

        $metaEfectiva = $metaConfig?->meta_efectiva !== null
            ? (float) $metaConfig->meta_efectiva
            : ($indicator->meta_valor !== null ? (float) $indicator->meta_valor : null);

        $ssId = (int) $this->authUser()->cen_hie_dependency_id;
        $yearId = $this->currentIndicatorYear()->id;

        $cargaPeriodicidad = app(MetIndicatorPeriodicityResolver::class)
            ->resolveForCarga($indicator, $ssId, $yearId)->value;

        $effectiveFieldTypeRows = app(MetFieldSsConfigService::class)->effectiveFieldTypeRowsForIndicator(
            $indicator,
            $ssId,
            $yearId,
        );

        return view('livewire.metas-instrument.monthly-data-entry', compact(
            'indicator',
            'monthlyFields',
            'trimestralFields',
            'semestralFields',
            'anualFields',
            'baselineFields',
            'metaEfectiva',
            'cargaPeriodicidad',
            'effectiveFieldTypeRows',
        ));
    }

    public function updatedMonthlyValues(): void
    {
        $this->refreshPreview();
    }

    public function updatedBaselineValues(): void
    {
        $this->refreshPreview();
    }

    public function periodDraftStatus(int $mes): string
    {
        $periodicFields = $this->applicablePeriodicFieldsForMonth($mes);

        if ($periodicFields->isEmpty()) {
            return 'na';
        }

        $completenessValidator = app(MetPeriodCaptureCompletenessValidator::class);
        $valuesByFieldId = $periodicFields->mapWithKeys(
            fn (MetDataField $field): array => [
                $field->id => data_get($this->monthlyValues, "{$mes}.{$field->id}"),
            ],
        )->all();

        $hasAnyValue = collect($valuesByFieldId)->contains(
            fn (mixed $value): bool => ! $completenessValidator->valueIsEmpty($value),
        );

        if (! $hasAnyValue) {
            return 'empty';
        }

        if ($completenessValidator->isPeriodIncomplete($valuesByFieldId, $periodicFields)) {
            return 'incomplete';
        }

        return 'complete';
    }

    /**
     * @return array{kind: string, message: string|null}
     */
    public function periodRowStatus(int $mes, DataFieldInputType $inputType): array
    {
        $draftStatus = $this->periodDraftStatus($mes);

        if ($draftStatus === 'incomplete') {
            return [
                'kind' => 'draft_incomplete',
                'message' => 'Complete todas las variables o vacíe el mes',
            ];
        }

        $periodo = MetInstrumentPeriodo::fromMonthAndInputType($mes, $inputType);

        if ($periodo !== null) {
            $errorMessage = $this->persistedCalculationErrorsByPeriodo[$periodo->value] ?? null;

            if ($errorMessage === null && $draftStatus === 'complete') {
                $errorMessage = $this->draftCalculationErrorMessageForPeriod($mes, $inputType);
            }

            if ($errorMessage !== null) {
                return [
                    'kind' => 'calculation_error',
                    'message' => $errorMessage,
                ];
            }
        }

        if ($draftStatus !== 'complete' && isset($this->persistedIncompleteMonths[$mes])) {
            return [
                'kind' => 'persisted_incomplete',
                'message' => 'Carga incompleta — complete o vacíe el mes',
            ];
        }

        if ($draftStatus === 'empty') {
            return [
                'kind' => 'empty',
                'message' => null,
            ];
        }

        return [
            'kind' => 'ready',
            'message' => 'Listo para guardar',
        ];
    }

    public function saveRow(int $mes): void
    {
        if (! $this->canEdit) {
            $this->addError('general', $this->accessMessage ?: 'No tiene permisos para guardar datos en este indicador.');
            $this->flashMonthlyNotice('danger', $this->accessMessage ?: 'No tiene permisos para guardar datos en este indicador.');

            return;
        }

        if ($mes < 1 || $mes > 12) {
            $this->addError('general', 'Mes inválido para guardar datos.');
            $this->flashMonthlyNotice('danger', 'Mes inválido para guardar datos.');

            return;
        }

        $this->resetValidation(["month.{$mes}"]);

        $periodicFields = $this->applicablePeriodicFieldsForMonth($mes);
        $this->validate($this->buildMonthlyRules($periodicFields, $mes));

        $completenessValidator = app(MetPeriodCaptureCompletenessValidator::class);
        $valuesByFieldId = $periodicFields->mapWithKeys(
            fn (MetDataField $field): array => [
                $field->id => data_get($this->monthlyValues, "{$mes}.{$field->id}"),
            ],
        )->all();

        $validationError = $completenessValidator->validateManualPeriod($valuesByFieldId, $periodicFields);

        if ($validationError !== null) {
            $monthMessage = $this->monthLabel($mes).': complete todos los campos (0 es válido) o vacíe el mes entero.';
            $this->addError("month.{$mes}", $monthMessage);
            $this->flashMonthlyNotice('danger', 'No se guardó '.$this->monthLabel($mes).'. Revise la fila resaltada.');

            return;
        }

        $savedAnyValue = false;

        foreach ($periodicFields as $field) {
            $value = data_get($this->monthlyValues, "{$mes}.{$field->id}");
            $normalized = $this->normalizeNullableNumeric($value);
            if ($normalized !== null) {
                $savedAnyValue = true;
            }

            $observation = data_get($this->monthlyObservations, "{$mes}.{$field->id}");

            $record = MetMonthlyData::query()->firstOrNew([
                'met_data_field_id' => $field->id,
                'cen_center_id' => $this->centerId,
                'year_id' => $this->currentIndicatorYear()->id,
                'mes' => $mes,
                'met_measurement_unit_id' => $this->measurementUnitId,
            ]);

            $record->valor = $normalized;
            $record->observacion = $this->normalizeNullableString($observation);
            $record->setRelation('dataField', $field);
            $record->save();
        }

        $this->loadExistingData();
        $this->refreshPreview();
        $this->scheduleRecalculationAfterSave();
        $this->refreshPeriodRowAlerts();

        if ($savedAnyValue) {
            $this->flashMonthlyNotice('success', $this->monthLabel($mes).': datos guardados correctamente.');
        } else {
            $this->flashMonthlyNotice('warning', $this->monthLabel($mes).': período vaciado. No quedan datos cargados para ese mes.');
        }
    }

    public function saveFieldRow(int $fieldId): void
    {
        if (! $this->canEdit) {
            $this->addError('general', $this->accessMessage ?: 'No tiene permisos para guardar datos en este indicador.');

            return;
        }

        $field = $this->getFields()->where('id', $fieldId)->first();

        if (! $field || ! $this->isPeriodicInputType($field->input_type)) {
            $this->addError('general', 'Campo inválido para guardar.');

            return;
        }

        $rules = [];

        foreach ($this->monthNumbersForInputType($field->input_type) as $mes) {
            $rules["monthlyValues.{$mes}.{$fieldId}"] = ['nullable', 'numeric'];
        }

        $this->validate($rules);

        $ssId = (int) $this->authUser()->cen_hie_dependency_id;
        $yearId = $this->currentIndicatorYear()->id;
        $completenessValidator = app(MetPeriodCaptureCompletenessValidator::class);

        foreach ($this->monthNumbersForInputType($field->input_type) as $mes) {
            $applicableFields = $completenessValidator->applicableFieldsForMonth(
                $this->getFields(),
                $mes,
                $ssId,
                $yearId,
            );

            $combinedValues = [];
            foreach ($applicableFields as $applicableField) {
                $combinedValues[$applicableField->id] = data_get(
                    $this->monthlyValues,
                    "{$mes}.{$applicableField->id}",
                );
            }

            $validationError = $completenessValidator->validateManualPeriod($combinedValues, $applicableFields);
            if ($validationError !== null) {
                $this->addError("month.{$mes}", $this->monthLabel($mes).': complete todos los campos (0 es válido) o vacíe el mes entero.');
                $this->flashMonthlyNotice('danger', 'No se guardó '.$this->monthLabel($mes).'. Revise la fila resaltada.');

                return;
            }
        }

        $savedAnyValue = false;

        foreach ($this->monthNumbersForInputType($field->input_type) as $mes) {
            $value = data_get($this->monthlyValues, "{$mes}.{$fieldId}");
            $normalized = $this->normalizeNullableNumeric($value);
            if ($normalized !== null) {
                $savedAnyValue = true;
            }

            $record = MetMonthlyData::query()->firstOrNew([
                'met_data_field_id' => $fieldId,
                'cen_center_id' => $this->centerId,
                'year_id' => $this->currentIndicatorYear()->id,
                'mes' => $mes,
                'met_measurement_unit_id' => $this->measurementUnitId,
            ]);

            $record->valor = $normalized;
            $record->observacion = null;
            $record->setRelation('dataField', $field);
            $record->save();
        }

        $this->loadExistingData();
        $this->refreshPreview();
        $this->scheduleRecalculationAfterSave();
        $this->refreshPeriodRowAlerts();

        if ($savedAnyValue) {
            $this->flashMonthlyNotice('success', $field->variable_key.': datos del campo guardados correctamente.');
        } else {
            $this->flashMonthlyNotice('warning', $field->variable_key.': períodos vaciados. No quedan datos cargados para esos meses.');
        }
    }

    public function saveBaselineRow(): void
    {
        if (! $this->canEdit) {
            $this->addError('general', $this->accessMessage ?: 'No tiene permisos para guardar datos en este indicador.');

            return;
        }

        $baselineFields = $this->getFields()->where('input_type', DataFieldInputType::LineaBase);
        $this->validate($this->buildBaselineRules($baselineFields));

        foreach ($baselineFields as $field) {
            $baselineQuery = MetMonthlyData::query()
                ->where('met_data_field_id', $field->id)
                ->where('cen_center_id', $this->centerId)
                ->where('year_id', $this->currentIndicatorYear()->id)
                ->whereNull('mes');

            if ($this->measurementUnitId !== null) {
                $baselineQuery->where('met_measurement_unit_id', $this->measurementUnitId);
            } else {
                $baselineQuery->whereNull('met_measurement_unit_id');
            }

            $record = $baselineQuery->first();

            if (! $record) {
                $record = new MetMonthlyData([
                    'met_data_field_id' => $field->id,
                    'cen_center_id' => $this->centerId,
                    'year_id' => $this->currentIndicatorYear()->id,
                    'mes' => null,
                    'met_measurement_unit_id' => $this->measurementUnitId,
                ]);
            }

            $record->valor = $this->normalizeNullableNumeric($this->baselineValues[$field->id] ?? null);
            $record->observacion = $this->normalizeNullableString($this->baselineObservations[$field->id] ?? null);
            $record->setRelation('dataField', $field);
            $record->save();
        }

        $this->loadExistingData();
        $this->refreshPreview();
        $this->scheduleRecalculationAfterSave();
        $this->flashMonthlyNotice('success', 'Línea base guardada correctamente.');
    }

    private function scheduleRecalculationAfterSave(): void
    {
        if (! $this->canEdit) {
            return;
        }

        app(MetRecalculationDispatcher::class)->scheduleAutoevaluacionScope(
            new MetRecalculationScope(
                yearId: $this->currentIndicatorYear()->id,
                dependencyId: (int) $this->authUser()->cen_hie_dependency_id,
                indicatorTypeId: $this->indicatorTypeId,
                centerId: $this->centerId,
                metIndicatorId: $this->indicatorId,
                measurementUnitId: $this->measurementUnitId,
            ),
        );
    }

    /**
     * @param  Collection<int, MetDataField>  $fields
     */
    private function initializeInputArrays(Collection $fields): void
    {
        $periodicFields = $fields->filter(fn (MetDataField $field): bool => $this->isPeriodicInputType($field->input_type));
        $baselineFields = $fields->where('input_type', DataFieldInputType::LineaBase);

        foreach (array_keys($this->months) as $month) {
            $this->monthlyValues[$month] = [];
            $this->monthlyObservations[$month] = [];

            foreach ($periodicFields as $field) {
                $this->monthlyValues[$month][$field->id] = null;
                $this->monthlyObservations[$month][$field->id] = '';
            }
        }

        foreach ($baselineFields as $field) {
            $this->baselineValues[$field->id] = null;
            $this->baselineObservations[$field->id] = '';
        }
    }

    private function loadExistingData(): void
    {
        $recordsQuery = MetMonthlyData::query()
            ->currentYear()
            ->where('cen_center_id', $this->centerId)
            ->whereIn('met_data_field_id', $this->getFields()->pluck('id'));

        if ($this->measurementUnitId !== null) {
            $recordsQuery->where('met_measurement_unit_id', $this->measurementUnitId);
        } else {
            $recordsQuery->whereNull('met_measurement_unit_id');
        }

        $records = $recordsQuery->get();

        foreach ($records as $record) {
            if ($record->mes === null) {
                $this->baselineValues[$record->met_data_field_id] = MetMonthlyDataInputFormatter::formatForInput($record->valor);
                $this->baselineObservations[$record->met_data_field_id] = $record->observacion ?? '';

                continue;
            }

            $this->monthlyValues[$record->mes][$record->met_data_field_id] = MetMonthlyDataInputFormatter::formatForInput($record->valor);
            $this->monthlyObservations[$record->mes][$record->met_data_field_id] = $record->observacion ?? '';
        }
    }

    private function refreshPeriodRowAlerts(): void
    {
        $ssId = (int) $this->authUser()->cen_hie_dependency_id;
        $yearId = $this->currentIndicatorYear()->id;

        $this->persistedCalculationErrorsByPeriodo = app(MetIndicatorCalculationErrorService::class)
            ->errorsByPeriodoForCenter(
                $this->indicatorId,
                $this->centerId,
                $ssId,
                $yearId,
                $this->measurementUnitId,
            );

        $incompleteMonths = app(MetIndicatorIncompletePeriodService::class)
            ->incompleteMonthNumbersForCenter(
                $this->indicatorId,
                $this->centerId,
                $ssId,
                $yearId,
                $this->measurementUnitId,
            );

        $this->persistedIncompleteMonths = array_fill_keys($incompleteMonths, true);
    }

    private function draftCalculationErrorMessageForPeriod(int $mes, DataFieldInputType $inputType): ?string
    {
        $periodo = MetInstrumentPeriodo::fromMonthAndInputType($mes, $inputType);

        if ($periodo === null) {
            return null;
        }

        $indicator = MetIndicator::with(['dataFields', 'formula'])
            ->where('id', $this->indicatorId)
            ->where('year_id', $this->currentIndicatorYear()->id)
            ->first();

        if ($indicator === null || ! $indicator->formula || empty($indicator->formula->expression)) {
            return null;
        }

        $variables = [];

        foreach ($this->getFields() as $field) {
            if ($field->input_type === DataFieldInputType::LineaBase) {
                $raw = $this->baselineValues[$field->id] ?? null;
                $variables[$field->variable_key] = is_numeric($raw) ? (float) $raw : null;

                continue;
            }

            if (! in_array($mes, $this->monthNumbersForInputType($field->input_type), true)) {
                continue;
            }

            $raw = data_get($this->monthlyValues, "{$mes}.{$field->id}");
            $variables[$field->variable_key] = is_numeric($raw) ? (float) $raw : null;
        }

        $aggregator = app(MetDataAggregatorService::class);
        $primaryComponenteValues = $aggregator->resolvePrimaryComponenteValues($indicator, $variables);

        $evaluation = app(MetIndicatorEvaluationService::class)->evaluate(
            $indicator,
            $periodo->value,
            $variables,
            $primaryComponenteValues['numerador'],
            $primaryComponenteValues['denominador'],
        );

        if ($evaluation['ok'] || ! filled($evaluation['message'])) {
            return null;
        }

        if (MetResult::storedErrorMessageIsNonCalculationAlert($evaluation['message'])) {
            return null;
        }

        return $evaluation['message'];
    }

    private function refreshPreview(): void
    {
        $indicator = MetIndicator::with(['dataFields', 'formula'])
            ->where('id', $this->indicatorId)
            ->where('year_id', $this->currentIndicatorYear()->id)
            ->firstOrFail();

        if (! $indicator->formula || empty($indicator->formula->expression)) {
            $this->preview = 'N/D';

            return;
        }

        $variables = [];

        foreach ($this->getFields() as $field) {
            if ($field->input_type === DataFieldInputType::LineaBase) {
                $raw = $this->baselineValues[$field->id] ?? null;
                $variables[$field->variable_key] = is_numeric($raw) ? (float) $raw : null;

                continue;
            }

            if ($field->aggregation_type !== null && ! $field->aggregation_type->isAccumulative()) {
                $lastValue = null;

                foreach ($this->monthNumbersForInputType($field->input_type) as $mes) {
                    $raw = $this->monthlyValues[$mes][$field->id] ?? null;
                    if (is_numeric($raw)) {
                        $lastValue = (float) $raw;
                    }
                }

                $variables[$field->variable_key] = $lastValue;
            } else {
                $total = null;
                foreach ($this->monthNumbersForInputType($field->input_type) as $mes) {
                    $raw = $this->monthlyValues[$mes][$field->id] ?? null;
                    if (is_numeric($raw)) {
                        $total = ($total ?? 0.0) + (float) $raw;
                    }
                }

                $variables[$field->variable_key] = $total;
            }
        }

        $numeradorFields = $this->getFields()->where('componente', DataFieldComponente::Numerador)->values();
        $denominadorFields = $this->getFields()->where('componente', DataFieldComponente::Denominador)->values();

        $primaryNumerador = $numeradorFields->count() === 1
            ? ($variables[$numeradorFields->first()->variable_key] ?? null)
            : null;
        $primaryDenominador = $denominadorFields->count() === 1
            ? ($variables[$denominadorFields->first()->variable_key] ?? null)
            : null;

        $evaluationService = app(MetIndicatorEvaluationService::class);
        $result = $evaluationService->evaluate(
            $indicator,
            'MES-01',
            $variables,
            $primaryNumerador,
            $primaryDenominador,
        );

        if (! $result['ok'] || $result['result'] === null) {
            $this->preview = $result['message'];

            return;
        }

        $decimals = max(0, min(10, (int) ($indicator->decimales ?? 2)));
        $this->preview = number_format((float) $result['result'], $decimals, '.', '');
    }

    /**
     * @param  Collection<int, MetDataField>  $monthlyFields
     * @return array<string, array<int, string>>
     */
    private function buildMonthlyRules(Collection $monthlyFields, int $mes): array
    {
        $rules = [];

        foreach ($monthlyFields as $field) {
            $rules["monthlyValues.{$mes}.{$field->id}"] = ['nullable', 'numeric'];
            $rules["monthlyObservations.{$mes}.{$field->id}"] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    /**
     * @param  Collection<int, MetDataField>  $baselineFields
     * @return array<string, array<int, string>>
     */
    private function buildBaselineRules(Collection $baselineFields): array
    {
        $rules = [];

        foreach ($baselineFields as $field) {
            $rules["baselineValues.{$field->id}"] = ['nullable', 'numeric'];
            $rules["baselineObservations.{$field->id}"] = ['nullable', 'string', 'max:2000'];
        }

        return $rules;
    }

    private function isPeriodicInputType(DataFieldInputType $inputType): bool
    {
        return $inputType->isPeriodic();
    }

    /**
     * @return list<int>
     */
    private function monthNumbersForInputType(DataFieldInputType $inputType): array
    {
        return $inputType->monthNumbers();
    }

    private function normalizeNullableNumeric(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return Collection<int, MetDataField>
     */
    private function applicablePeriodicFieldsForMonth(int $mes): Collection
    {
        return $this->getFields()->filter(
            fn (MetDataField $field): bool => $this->isPeriodicInputType($field->input_type)
                && in_array($mes, $this->monthNumbersForInputType($field->input_type), true),
        )->values();
    }

    private function monthLabel(int $mes): string
    {
        return MetInstrumentPeriodo::tryFrom(sprintf('MES-%02d', $mes))?->etiquetaCorta() ?? (string) $mes;
    }

    private function flashMonthlyNotice(string $type, string $message): void
    {
        $toastType = match ($type) {
            'danger' => 'error',
            'warning' => 'warning',
            'success' => 'success',
            default => 'info',
        };

        $this->dispatch('toast', type: $toastType, message: $message);
    }

    /**
     * @return Collection<int, MetDataField>
     */
    private function getFields(): Collection
    {
        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicatorId)
            ->orderBy('orden')
            ->get();

        return app(MetFieldSsConfigService::class)->applyEffectiveTypes(
            $fields,
            (int) $this->authUser()->cen_hie_dependency_id,
            $this->currentIndicatorYear()->id,
        );
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
