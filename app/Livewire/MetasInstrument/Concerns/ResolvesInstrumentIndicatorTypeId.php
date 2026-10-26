<?php

namespace App\Livewire\MetasInstrument\Concerns;

use App\Models\IndicatorType;

trait ResolvesInstrumentIndicatorTypeId
{
    protected function resolveInstrumentIndicatorTypeId(?int $indicatorTypeId = null): int
    {
        if ($indicatorTypeId !== null) {
            return $indicatorTypeId;
        }

        $route = request()->route('indicatorType');
        if ($route instanceof IndicatorType) {
            return (int) $route->id;
        }

        $fallbackId = IndicatorType::query()->where('route', 'metas-18834')->value('id');
        if ($fallbackId === null) {
            abort(404);
        }

        return (int) $fallbackId;
    }
}
