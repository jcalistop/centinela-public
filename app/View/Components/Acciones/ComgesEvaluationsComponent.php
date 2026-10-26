<?php

namespace App\View\Components\Acciones;

use App\Models\ComAction;
use App\Models\ComEvaluation;
use App\Support\Comges\ComgesYearMode;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ComgesEvaluationsComponent extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ComAction $action
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $evaluations = ComEvaluation::where('com_action_id', $this->action->id)->get();
        $view = ComgesYearMode::currentUsesScoreScale()
            ? 'components.comges.acciones.comges-evaluations-component'
            : 'components.comges.acciones.legacy.comges-evaluations-component';

        return view($view, compact('evaluations'));
    }
}
