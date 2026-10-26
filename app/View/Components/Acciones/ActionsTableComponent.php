<?php

namespace App\View\Components\Acciones;

use App\Enums\Corte\CutoffClave;
use App\Models\ComAction;
use App\Models\ComSensitivityDependency;
use App\Support\Comges\ComgesCutoffUiRules;
use App\Support\Comges\ComgesYearMode;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use TCG\Voyager\Facades\Voyager;

class ActionsTableComponent extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ComAction $action,
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $cenHieDependencyId = Auth::user()->cen_hie_dependency_id;
        $useRole = Auth::user()->role->name;

        $comAction = ComAction::with([
            'comSensitivityTables',
            'comSensitivityDependencies',
        ])->find($this->action->id);

        // dd($comAction->comSensitivityTables->count() == 1);

        if (
            $comAction->comSensitivityDependencies->where('cen_hie_dependency_id', $cenHieDependencyId)->isEmpty()
            && $useRole !== 'Administrador'
            && $useRole !== 'Parametrizador'
        ) {
            if ($comAction->comSensitivityTables->count() == 1) {
                ComSensitivityDependency::create([
                    'com_sensitivity_table_id' => $comAction->comSensitivityTables->first()->id,
                    'com_action_id' => $comAction->id,
                    'cen_hie_dependency_id' => $cenHieDependencyId,
                ]);
            } else {

            }
        }

        $dataType = Voyager::model('DataType')->where('slug', 'acciones')->first();
        $action = $this->action->loadMissing('comIndicatorCutoff.cutoff');
        $useScoreScale = ComgesYearMode::currentUsesScoreScale();
        $cutoffClave = $action->comIndicatorCutoff->cutoff->clave;
        $hideSensitivityAndEvaluationsRows = $useScoreScale
            && ComgesYearMode::apelacionActionsHideSensitivityAndEvaluations()
            && $cutoffClave === CutoffClave::Apelacion;
        $usesMonitoringCutoffUi = ComgesYearMode::currentUsesMonitoringCutoffUi();
        if ($useScoreScale && $usesMonitoringCutoffUi) {
            $showVerifiableEvaluation = ComgesCutoffUiRules::showsVerifiableEvaluationDocuments($action->comIndicatorCutoff);
            $showVerifiableAppeal = ComgesCutoffUiRules::showsVerifiableAppealDocuments($action->comIndicatorCutoff);
        } elseif ($useScoreScale) {
            $isIndicadorOrRequisito = $cutoffClave === CutoffClave::Indicador
                || $cutoffClave === CutoffClave::Requisito;
            $showVerifiableEvaluation = $isIndicadorOrRequisito;
            $showVerifiableAppeal = $cutoffClave === CutoffClave::Apelacion;
        } else {
            $showVerifiableEvaluation = false;
            $showVerifiableAppeal = false;
        }
        $view = $useScoreScale
            ? 'components.comges.acciones.actions-table-component'
            : 'components.comges.acciones.legacy.actions-table-component';

        return view($view, compact(
            'action',
            'dataType',
            'useScoreScale',
            'hideSensitivityAndEvaluationsRows',
            'showVerifiableEvaluation',
            'showVerifiableAppeal',
        ));
    }
}
