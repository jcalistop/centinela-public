<?php

namespace App\View\Components;

use App\Models\Year;
use App\Support\Voyager\YearDropdownCatalog;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DropdownFromModelComponent extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $model,
        public string $field,
        public string $route
    ) {}

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        $data = $this->model === Year::class
            ? YearDropdownCatalog::all()
            : app($this->model)->all();

        return view('components.dropdown-from-model-component', compact('data'));
    }
}
