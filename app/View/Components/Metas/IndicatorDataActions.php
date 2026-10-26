<?php

namespace App\View\Components\Metas;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class IndicatorDataActions extends Component
{
    public function __construct(
        public string $wireClick,
        public ?string $downloadUrl = null,
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.metas.indicator-data-actions');
    }
}
