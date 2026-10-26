<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\DecimalesModo;
use App\Enums\MetaOperator;
use App\Enums\Metas\DataFieldAggregationType;
use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\DataFieldInputType;
use App\Enums\Metas\DataFieldSource;
use App\Enums\Metas\MetIndicatorPriority;
use App\Enums\Metas\UnidadMedida;
use App\Livewire\MetasInstrument\Concerns\AssertsMetIndicatorCatalogScope;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Services\Indicators\IndicatorAmbitoAssignmentService;
use App\Services\Metas\MetIndicatorCatalogPersistenceService;
use App\Support\Metas\ObjetivoHtml;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class IndicatorCatalogFormComponent extends Component
{
    use AssertsMetIndicatorCatalogScope;
    use ResolvesInstrumentIndicatorTypeId;

    public int $indicatorTypeId = 0;

    public ?int $indicatorId = null;

    public string $clave = '';

    public string $nombre = '';

    public string $objetivo = '';

    public string $metaAnualDescripcion = '';

    public string $metaValor = '';

    public string $metaOperador = '>=';

    public string $metaReglas = '';

    public string $umbralInferior = '0';

    public string $umbralSuperior = '100';

    /** @var array<int, array{id: int|null, label: string, componente: string, input_type: string, aggregation_type: string|null, fuente: string|null}> */
    public array $fields = [];

    public string $formulaJson = '[]';

    public string $formulaDescription = '';

    public int $formulaRevision = 0;

    public string $newFieldLabel = '';

    public string $newFieldComponente = DataFieldComponente::Numerador->value;

    public string $newFieldInputType = DataFieldInputType::Mensual->value;

    public string $newFieldAggregationType = DataFieldAggregationType::Sumatoria->value;

    public string $newFieldFuente = '';

    public string $nivelAplicacion = '';

    public string $exclusiones = '';

    public string $unidadMedida = '%';

    public int $decimales = 2;

    public string $decimalesModo = 'redondear';

    public bool $estado = true;

    public string $prioridad = 'sin_prioridad';

    /** @var list<int> */
    public array $selectedAmbitoIds = [];

    public function mount(?int $indicatorTypeId = null, ?int $indicatorId = null, ?IndicatorType $indicatorType = null, ?MetIndicator $indicator = null): void
    {
        $this->authorizeParametrizador();

        $resolvedTypeId = ($indicatorType instanceof IndicatorType && $indicatorType->exists)
            ? $indicatorType->id
            : ($indicatorTypeId ?? ($this->indicatorTypeId > 0 ? $this->indicatorTypeId : null));
        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId(
            $resolvedTypeId !== null ? (int) $resolvedTypeId : null
        );

        $indicatorTypeModel = $this->resolvedIndicatorType();
        $this->assertMetasInstrumentIndicatorType($indicatorTypeModel);

        if (filled($indicatorId)) {
            $indicatorModel = MetIndicator::query()->findOrFail($indicatorId);
            $this->assertIndicatorInScope($indicatorModel, $indicatorTypeModel);
            $this->indicatorId = (int) $indicatorId;
            $this->loadFromIndicator($indicatorModel);
        } elseif ($indicator instanceof MetIndicator && $indicator->exists) {
            $this->assertIndicatorInScope($indicator, $indicatorTypeModel);
            $this->indicatorId = $indicator->id;
            $this->loadFromIndicator($indicator);
        }
    }

    public function updatedMetaValor(string $value): void
    {
        if ($value === '' || ! is_numeric($value)) {
            return;
        }

        $this->metaValor = number_format(round((float) $value, 2), 2, '.', '');
    }

    public function save(): void
    {
        $this->validateForm();

        $data = $this->indicatorPayload();

        try {
            $result = app(MetIndicatorCatalogPersistenceService::class)->persist(
                $data,
                $this->fields,
                $this->formulaJson,
                $this->indicatorId,
                $this->indicatorTypeId,
                $this->currentIndicatorYear(),
                $this->selectedAmbitoIds,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        session()->flash('message', 'Indicador guardado correctamente.');
        session()->flash('alert-type', 'success');

        $indicatorType = $this->resolvedIndicatorType();
        $this->redirect(route('metas.instrument.indicators.show', [
            $indicatorType,
            $result['indicator_id'],
        ]));
    }

    public function addField(): void
    {
        $persistence = app(MetIndicatorCatalogPersistenceService::class);

        $this->validate([
            'newFieldLabel' => ['required', 'string', 'max:255'],
            'newFieldComponente' => ['required', Rule::enum(DataFieldComponente::class)],
            'newFieldInputType' => ['required', Rule::enum(DataFieldInputType::class)],
            'newFieldAggregationType' => [
                Rule::requiredIf(fn () => DataFieldInputType::tryFrom($this->newFieldInputType)?->requiresAggregationType() ?? false),
                'nullable',
                Rule::enum(DataFieldAggregationType::class),
            ],
            'newFieldFuente' => ['nullable', Rule::in($persistence->validDataFieldSourceValues())],
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
            'aggregation_type' => $persistence->resolveAggregationTypeValue($this->newFieldInputType, $this->newFieldAggregationType),
            'fuente' => $this->newFieldFuente !== '' ? $this->newFieldFuente : null,
        ];

        $this->newFieldLabel = '';
        $this->newFieldComponente = DataFieldComponente::Numerador->value;
        $this->newFieldInputType = DataFieldInputType::Mensual->value;
        $this->newFieldAggregationType = DataFieldAggregationType::Sumatoria->value;
        $this->newFieldFuente = '';
    }

    public function removeField(int $index): void
    {
        $field = $this->fields[$index] ?? null;

        if ($field === null) {
            return;
        }

        if ($field['id'] && app(MetIndicatorCatalogPersistenceService::class)->fieldHasMonthlyData((int) $field['id'])) {
            $this->addError('fields', 'No se puede eliminar un campo con datos de carga existentes.');

            return;
        }

        array_splice($this->fields, $index, 1);
        $this->clearFormulaState();
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

    public function render(): View
    {
        return view('livewire.metas-instrument.indicator-catalog-form-component', [
            'indicatorType' => $this->resolvedIndicatorType(),
            'dataFieldSourceOptions' => DataFieldSource::options(),
            'dataFieldInputTypeOptions' => DataFieldInputType::options(),
            'dataFieldAggregationTypeOptions' => DataFieldAggregationType::options(),
            'unidadMedidaOptions' => UnidadMedida::options(),
            'prioridadOptions' => MetIndicatorPriority::options(),
            'indicatorAmbitoOptions' => app(IndicatorAmbitoAssignmentService::class)->activeCatalog(),
            'isEditing' => $this->indicatorId !== null,
            'editingId' => $this->indicatorId ?? 'new',
        ]);
    }

    private function resolvedIndicatorType(): IndicatorType
    {
        return IndicatorType::query()->findOrFail($this->indicatorTypeId);
    }

    private function usesIndicatorPriority(): bool
    {
        return $this->resolvedIndicatorType()->route === 'metas-19664';
    }

    private function validateForm(): void
    {
        $rules = [
            'clave' => ['required', 'string', 'max:255', Rule::unique('met_indicators', 'clave')->ignore($this->indicatorId)],
            'nombre' => ['required', 'string', 'max:255'],
            'objetivo' => ['nullable', 'string'],
            'metaAnualDescripcion' => ['nullable', 'string'],
            'metaValor' => ['required', 'numeric'],
            'metaOperador' => ['required', Rule::enum(MetaOperator::class)],
            'umbralInferior' => ['required', 'numeric', 'between:0,100', 'lte:umbralSuperior'],
            'umbralSuperior' => ['required', 'numeric', 'between:0,100', 'gte:umbralInferior'],
            'nivelAplicacion' => ['required', 'string'],
            'exclusiones' => ['nullable', 'string'],
            'unidadMedida' => ['required', Rule::enum(UnidadMedida::class)],
            'decimales' => ['required', 'integer', 'min:0', 'max:10'],
            'decimalesModo' => ['required', Rule::enum(DecimalesModo::class)],
            'estado' => ['required', 'boolean'],
        ];

        if ($this->usesIndicatorPriority()) {
            $rules['prioridad'] = ['required', Rule::enum(MetIndicatorPriority::class)];
        }

        $this->validate($rules, [
            'metaValor.required' => 'El valor meta es obligatorio.',
            'metaOperador.required' => 'El operador es obligatorio.',
            'unidadMedida.required' => 'La unidad de medida es obligatoria.',
            'nivelAplicacion.required' => 'El nivel de aplicación es obligatorio.',
            'umbralInferior.between' => 'Los umbrales deben estar en el rango 0–100.',
            'umbralSuperior.between' => 'Los umbrales deben estar en el rango 0–100.',
            'umbralInferior.lte' => 'El umbral inferior no puede ser mayor que el umbral superior.',
            'umbralSuperior.gte' => 'El umbral superior no puede ser menor que el umbral inferior.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function indicatorPayload(): array
    {
        return [
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'objetivo' => ObjetivoHtml::sanitize($this->objetivo),
            'meta_anual_descripcion' => $this->metaAnualDescripcion ?: null,
            'meta_valor' => round((float) $this->metaValor, 2),
            'meta_operador' => $this->metaOperador,
            'meta_reglas' => $this->metaReglas !== '' ? json_decode($this->metaReglas, true) : null,
            'umbral_inferior' => $this->umbralInferior,
            'umbral_superior' => $this->umbralSuperior,
            'nivel_aplicacion' => $this->nivelAplicacion,
            'exclusiones' => $this->exclusiones !== '' ? $this->exclusiones : null,
            'unidad_medida' => $this->unidadMedida,
            'decimales' => $this->decimales,
            'decimales_modo' => $this->decimalesModo,
            'estado' => $this->estado,
            'prioridad' => $this->usesIndicatorPriority()
                ? $this->prioridad
                : MetIndicatorPriority::SinPrioridad->value,
        ];
    }

    private function loadFromIndicator(MetIndicator $indicator): void
    {
        $persistence = app(MetIndicatorCatalogPersistenceService::class);

        $this->clave = $indicator->clave;
        $this->nombre = $indicator->nombre;
        $this->objetivo = ObjetivoHtml::sanitize($indicator->objetivo) ?? '';
        $this->metaAnualDescripcion = (string) $indicator->meta_anual_descripcion;
        $this->metaValor = $indicator->meta_valor !== null
            ? number_format((float) $indicator->meta_valor, 2, '.', '')
            : '';
        $this->metaOperador = $indicator->meta_operador?->value ?? MetaOperator::MayorIgual->value;
        $this->metaReglas = $indicator->meta_reglas !== null
            ? json_encode($indicator->meta_reglas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
        $this->umbralInferior = $indicator->umbral_inferior !== null ? (string) $indicator->umbral_inferior : '0';
        $this->umbralSuperior = $indicator->umbral_superior !== null ? (string) $indicator->umbral_superior : '100';
        $this->fields = $persistence->loadFieldsFromDatabase($indicator->id);
        $this->formulaJson = $persistence->loadFormulaJson($indicator->id);
        $this->formulaDescription = $persistence->loadFormulaDescription($indicator->id);
        $this->formulaRevision++;
        $this->nivelAplicacion = (string) $indicator->nivel_aplicacion;
        $this->exclusiones = (string) ($indicator->exclusiones ?? '');
        $this->unidadMedida = UnidadMedida::tryFromStored($indicator->unidad_medida)?->value ?? '';
        $this->decimales = $indicator->decimales;
        $this->decimalesModo = $indicator->decimales_modo?->value ?? 'redondear';
        $this->estado = (bool) $indicator->estado;
        $this->prioridad = $indicator->prioridad?->value ?? MetIndicatorPriority::SinPrioridad->value;
        $this->selectedAmbitoIds = $indicator->indicatorAmbitos()
            ->pluck('indicator_ambitos.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function clearFormulaState(): void
    {
        $this->formulaJson = '[]';
        $this->formulaDescription = '';
        $this->formulaRevision++;
    }
}
