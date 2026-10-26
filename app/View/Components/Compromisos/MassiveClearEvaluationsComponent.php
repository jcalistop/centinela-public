<?php

namespace App\View\Components\Compromisos;

use App\Helpers\Helper;
use App\Models\Cutoff;
use App\Models\IndicatorTypeSetting;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class MassiveClearEvaluationsComponent extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct() {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $indicatorTypeSetting = IndicatorTypeSetting::where('cen_hie_dependency_id', Auth::user()->cen_hie_dependency_id)
            ->where('year_id', Helper::showCurrentYear()->id)
            ->where('indicator_type_id', 1)
            ->first();

        return view('components.compromisos.massive-clear-evaluations-component', ['cutoffs' => Cutoff::where('tipo_corte', $indicatorTypeSetting->tipo_evaluacion?->value)->get()]);
    }
}
