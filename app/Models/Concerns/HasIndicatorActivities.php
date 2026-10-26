<?php

namespace App\Models\Concerns;

use App\Enums\Indicators\IndicatorModuleType;
use App\Models\IndicatorActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasIndicatorActivities
{
    public function indicatorActivities(): HasMany
    {
        return $this->hasMany(IndicatorActivity::class, 'indicator_id')
            ->where('indicator_type', $this->indicatorActivityModule()->value);
    }

    abstract protected function indicatorActivityModule(): IndicatorModuleType;
}
