<?php

namespace App\Models\Concerns;

use App\Models\IndicatorAmbito;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasIndicatorAmbitos
{
    /**
     * @return MorphToMany<IndicatorAmbito, $this>
     */
    public function indicatorAmbitos(): MorphToMany
    {
        return $this->morphToMany(
            IndicatorAmbito::class,
            'indicatable',
            'indicator_ambito_assignments'
        )->withTimestamps();
    }
}
