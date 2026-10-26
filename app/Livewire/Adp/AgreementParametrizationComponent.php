<?php

namespace App\Livewire\Adp;

use App\Livewire\Adp\Traits\ResolvesAdpInstrument;
use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpIndicatorFormula;
use App\Models\AdpIndicatorTarget;
use App\Models\AdpObjective;
use App\Services\Adp\AdpIndicatorCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class AgreementParametrizationComponent extends Component
{
    use ResolvesAdpInstrument;

    public ?int $selectedAgreementId = null;

    public ?int $selectedPeriodId = null;

    // Objective form
    public bool $showObjectiveForm = false;

    public ?int $editingObjectiveId = null;

    public string $objClave = '';

    public string $objNombre = '';

    public ?string $objDescripcion = null;

    public int $objOrden = 1;

    // Indicator form
    public ?int $editingIndicatorId = null;

    public ?int $catalogObjectiveId = null;

    public string $indClave = '';

    public string $indNombre = '';

    public string $indCalculationMethod = 'percent';

    public string $indPeriodicidad = 'ANUAL';

    public ?string $indMetaValor = null;

    public ?string $indMetaOperador = null;

    // Formula fields (inline)
    public string $indFormulaExpression = '[]';

    public ?string $indFormulaDescripcion = null;

    // Target fields
    public ?string $indWeightPercent = null;

    public ?string $indTargetValue = null;

    public ?string $indTargetOperador = null;

    // Catalog cloning
    public ?int $cloneCatalogItemId = null;

    protected function rules(): array
    {
        return [
            'selectedAgreementId' => 'nullable|exists:adp_agreements,id',
            'selectedPeriodId' => 'nullable|exists:adp_agreement_periods,id',
            // Objective rules
            'objClave' => 'required|string|max:64',
            'objNombre' => 'required|string|max:255',
            'objDescripcion' => 'nullable|string|max:10000',
            'objOrden' => 'required|integer|min:1',
            // Indicator rules
            'indClave' => 'required|string|max:64',
            'indNombre' => 'required|string|max:255',
            'indCalculationMethod' => 'required|in:percent,sensitivity,dichotomic',
            'indPeriodicidad' => 'required|string|max:24',
            'indMetaValor' => 'nullable|numeric',
            'indMetaOperador' => 'nullable|string|max:8',
            'indFormulaExpression' => 'nullable|json',
            'indFormulaDescripcion' => 'nullable|string|max:10000',
            'indWeightPercent' => 'nullable|numeric|min:0|max:100',
            'indTargetValue' => 'nullable|numeric',
            'indTargetOperador' => 'nullable|string|max:8',
        ];
    }

    public function mount(?int $indicatorTypeId = null): void
    {
        $this->indicatorTypeId = $this->resolveIndicatorTypeId($indicatorTypeId);
    }

    public function render(): View
    {
        $user = $this->authUser();
        $canEdit = $user->hasRole(['admin', 'control-gestion']);

        $agreements = AdpAgreement::query()
            ->with('assignment.user', 'assignment.position')
            ->get();

        $periods = collect();
        $objectives = collect();
        if ($this->selectedAgreementId !== null) {
            $periods = AdpAgreementPeriod::query()
                ->where('adp_agreement_id', $this->selectedAgreementId)
                ->orderBy('sequence')
                ->get();
        }
        if ($this->selectedPeriodId !== null) {
            $objectives = AdpObjective::query()
                ->where('adp_agreement_period_id', $this->selectedPeriodId)
                ->with('indicators.target', 'indicators.formula')
                ->orderBy('orden')
                ->get();
        }

        return view('livewire.adp.agreement-parametrization', compact(
            'agreements', 'periods', 'objectives', 'canEdit'
        ));
    }

    public function selectAgreement(int $id): void
    {
        $this->selectedAgreementId = $id;
        $this->selectedPeriodId = null;
        $this->resetObjectiveForm();
        $this->resetIndicatorForm();
    }

    public function selectPeriod(int $id): void
    {
        $this->selectedPeriodId = $id;
        $this->resetObjectiveForm();
        $this->resetIndicatorForm();
    }

    public function createObjective(): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);
        abort_unless($this->selectedPeriodId !== null, 403);

        $this->resetObjectiveForm();
        $this->objOrden = (AdpObjective::query()->where('adp_agreement_period_id', $this->selectedPeriodId)->max('orden') ?? 0) + 1;
        $this->showObjectiveForm = true;
    }

    public function editObjective(int $id): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);

        $objective = AdpObjective::query()->findOrFail($id);
        $this->editingObjectiveId = $objective->id;
        $this->objClave = $objective->clave;
        $this->objNombre = $objective->nombre;
        $this->objDescripcion = $objective->descripcion;
        $this->objOrden = $objective->orden;
        $this->showObjectiveForm = true;
    }

    public function saveObjective(): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);
        abort_unless($this->selectedPeriodId !== null, 403);

        $this->validateOnly('objClave');
        $this->validateOnly('objNombre');
        $this->validateOnly('objDescripcion');
        $this->validateOnly('objOrden');

        if ($this->editingObjectiveId !== null) {
            $objective = AdpObjective::query()->findOrFail($this->editingObjectiveId);
            $objective->update([
                'clave' => $this->objClave,
                'nombre' => $this->objNombre,
                'descripcion' => $this->objDescripcion,
                'orden' => $this->objOrden,
            ]);
            $this->dispatch('toast', type: 'success', message: 'Objetivo actualizado.');
        } else {
            AdpObjective::query()->create([
                'adp_agreement_period_id' => $this->selectedPeriodId,
                'clave' => $this->objClave,
                'nombre' => $this->objNombre,
                'descripcion' => $this->objDescripcion,
                'orden' => $this->objOrden,
            ]);
            $this->dispatch('toast', type: 'success', message: 'Objetivo creado.');
        }

        $this->resetObjectiveForm();
    }

    public function cancelObjectiveForm(): void
    {
        $this->resetObjectiveForm();
    }

    public function deleteObjective(int $id): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);

        $objective = AdpObjective::query()->findOrFail($id);
        $objective->delete();

        $this->dispatch('toast', type: 'success', message: 'Objetivo eliminado.');
    }

    public function createIndicator(int $objectiveId): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);

        $this->resetIndicatorForm();
        $this->catalogObjectiveId = $objectiveId;
    }

    public function editIndicator(int $id): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);

        $indicator = AdpIndicator::query()->with('formula', 'target')->findOrFail($id);
        $this->editingIndicatorId = $indicator->id;
        $this->catalogObjectiveId = $indicator->adp_objective_id;
        $this->indClave = $indicator->clave;
        $this->indNombre = $indicator->nombre;
        $this->indCalculationMethod = $indicator->calculation_method;
        $this->indPeriodicidad = $indicator->periodicidad;
        $this->indMetaValor = $indicator->meta_valor !== null ? (string) $indicator->meta_valor : null;
        $this->indMetaOperador = $indicator->meta_operador;
        $this->indFormulaExpression = $indicator->formula !== null ? json_encode($indicator->formula->expression) : '[]';
        $this->indFormulaDescripcion = $indicator->formula?->description;
        $this->indWeightPercent = $indicator->target !== null ? (string) $indicator->target->weight_percent : null;
        $this->indTargetValue = $indicator->target !== null ? (string) $indicator->target->target_value : null;
        $this->indTargetOperador = $indicator->target?->target_operador;
    }

    public function saveIndicator(): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);
        abort_unless($this->catalogObjectiveId !== null, 403);

        $this->validateOnly('indClave');
        $this->validateOnly('indNombre');
        $this->validateOnly('indCalculationMethod');
        $this->validateOnly('indPeriodicidad');
        $this->validateOnly('indFormulaExpression');

        $expression = json_decode($this->indFormulaExpression, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                'indFormulaExpression' => 'La expresión JSON no es válida.',
            ]);
        }

        DB::transaction(function () use ($expression): void {
            if ($this->editingIndicatorId !== null) {
                $indicator = AdpIndicator::query()->findOrFail($this->editingIndicatorId);
                $indicator->update([
                    'clave' => $this->indClave,
                    'nombre' => $this->indNombre,
                    'calculation_method' => $this->indCalculationMethod,
                    'periodicidad' => $this->indPeriodicidad,
                    'meta_valor' => $this->indMetaValor !== null ? (float) $this->indMetaValor : null,
                    'meta_operador' => $this->indMetaOperador,
                ]);

                // Update formula
                $formula = $indicator->formula;
                if ($formula !== null) {
                    $formula->update([
                        'expression' => $expression,
                        'description' => $this->indFormulaDescripcion,
                    ]);
                } else {
                    AdpIndicatorFormula::query()->create([
                        'adp_indicator_id' => $indicator->id,
                        'expression' => $expression,
                        'description' => $this->indFormulaDescripcion,
                    ]);
                }

                // Update target
                $target = $indicator->target;
                if ($target !== null) {
                    $target->update([
                        'weight_percent' => $this->indWeightPercent !== null ? (float) $this->indWeightPercent : null,
                        'target_value' => $this->indTargetValue !== null ? (float) $this->indTargetValue : null,
                        'target_operador' => $this->indTargetOperador,
                    ]);
                } else {
                    AdpIndicatorTarget::query()->create([
                        'adp_indicator_id' => $indicator->id,
                        'weight_percent' => $this->indWeightPercent !== null ? (float) $this->indWeightPercent : null,
                        'target_value' => $this->indTargetValue !== null ? (float) $this->indTargetValue : null,
                        'target_operador' => $this->indTargetOperador,
                    ]);
                }

                $this->dispatch('toast', type: 'success', message: 'Indicador actualizado.');
            } else {
                $indicator = AdpIndicator::query()->create([
                    'adp_objective_id' => $this->catalogObjectiveId,
                    'clave' => $this->indClave,
                    'nombre' => $this->indNombre,
                    'calculation_method' => $this->indCalculationMethod,
                    'periodicidad' => $this->indPeriodicidad,
                    'meta_valor' => $this->indMetaValor !== null ? (float) $this->indMetaValor : null,
                    'meta_operador' => $this->indMetaOperador,
                    'orden' => (AdpIndicator::query()->where('adp_objective_id', $this->catalogObjectiveId)->max('orden') ?? 0) + 1,
                    'estado' => true,
                ]);

                if (! empty($expression)) {
                    AdpIndicatorFormula::query()->create([
                        'adp_indicator_id' => $indicator->id,
                        'expression' => $expression,
                        'description' => $this->indFormulaDescripcion,
                    ]);
                }

                if ($this->indWeightPercent !== null) {
                    AdpIndicatorTarget::query()->create([
                        'adp_indicator_id' => $indicator->id,
                        'weight_percent' => (float) $this->indWeightPercent,
                        'target_value' => $this->indTargetValue !== null ? (float) $this->indTargetValue : null,
                        'target_operador' => $this->indTargetOperador,
                    ]);
                }

                $this->dispatch('toast', type: 'success', message: 'Indicador creado.');
            }
        });

        $this->resetIndicatorForm();
    }

    public function cancelIndicatorForm(): void
    {
        $this->resetIndicatorForm();
    }

    public function deleteIndicator(int $id): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);

        $indicator = AdpIndicator::query()->findOrFail($id);
        $indicator->delete();

        $this->dispatch('toast', type: 'success', message: 'Indicador eliminado.');
    }

    public function cloneFromCatalog(int $catalogItemId): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);
        abort_unless($this->catalogObjectiveId !== null, 403);

        $period = AdpAgreementPeriod::query()->findOrFail($this->selectedPeriodId);
        $agreement = AdpAgreement::query()->findOrFail($this->selectedAgreementId);

        $service = app(AdpIndicatorCatalogService::class);
        $service->cloneCatalogItemToObjective(
            catalogItemId: $catalogItemId,
            agreement: $agreement,
            period: $period,
            objectiveId: $this->catalogObjectiveId,
            userId: $user->id,
        );

        $this->cloneCatalogItemId = null;
        $this->dispatch('toast', type: 'success', message: 'Indicador clonado del catálogo.');
    }

    private function resetObjectiveForm(): void
    {
        $this->showObjectiveForm = false;
        $this->editingObjectiveId = null;
        $this->objClave = '';
        $this->objNombre = '';
        $this->objDescripcion = null;
        $this->objOrden = 1;
    }

    private function resetIndicatorForm(): void
    {
        $this->editingIndicatorId = null;
        $this->catalogObjectiveId = null;
        $this->indClave = '';
        $this->indNombre = '';
        $this->indCalculationMethod = 'percent';
        $this->indPeriodicidad = 'ANUAL';
        $this->indMetaValor = null;
        $this->indMetaOperador = null;
        $this->indFormulaExpression = '[]';
        $this->indFormulaDescripcion = null;
        $this->indWeightPercent = null;
        $this->indTargetValue = null;
        $this->indTargetOperador = null;
        $this->cloneCatalogItemId = null;
    }
}
