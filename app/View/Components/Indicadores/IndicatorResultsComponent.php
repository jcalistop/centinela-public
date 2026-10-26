<?php

namespace App\View\Components\Indicadores;

use App\Support\Comges\ComgesYearMode;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class IndicatorResultsComponent extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public mixed $dataTypeContent
    ) {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $view = ComgesYearMode::currentUsesScoreScale()
            ? 'components.comges.indicadores.indicator-results-component'
            : 'components.comges.indicadores.legacy.indicator-results-component';

        return view($view);
    }
}
