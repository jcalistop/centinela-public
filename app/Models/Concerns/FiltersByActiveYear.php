<?php

namespace App\Models\Concerns;

use App\Support\YearContext;
use Illuminate\Database\Eloquent\Builder;

trait FiltersByActiveYear
{
    public function scopeCurrentYear(Builder $query): Builder
    {
        $year = app(YearContext::class)->current();

        if ($year === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where($query->getModel()->getTable().'.year_id', $year->id);
    }
}
