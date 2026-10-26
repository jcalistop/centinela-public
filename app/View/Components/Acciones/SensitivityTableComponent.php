<?php

namespace App\View\Components\Acciones;

use App\Models\ComSensitivityTable;
use App\Support\Comges\ComgesYearMode;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class SensitivityTableComponent extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public int $id
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {

        $comSensitivityTable = ComSensitivityTable::find($this->id);
        $view = ComgesYearMode::currentUsesScoreScale()
            ? 'components.comges.acciones.sensitivity-table-component'
            : 'components.comges.acciones.legacy.sensitivity-table-component';

        return view($view, compact('comSensitivityTable'));
    }
}
