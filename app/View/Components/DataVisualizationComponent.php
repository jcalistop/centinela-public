<?php

namespace App\View\Components;

use App\Charts\DinamicXAxisChart;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class DataVisualizationComponent extends Component
{
    public function __construct(
        public ?string $data,
        public ?string $chartType
    ) {}

    public function render(): View|Closure|string
    {

        // Instancia y construye el gráfico
        $chart = (! is_null($this->chartType) && ! is_null($this->data))
            ? app(DinamicXAxisChart::class)->build($this->chartType, $this->data)
            : null;

        return view('components.data-visualization-component', [
            'chart' => $chart,
        ]);
    }
}
