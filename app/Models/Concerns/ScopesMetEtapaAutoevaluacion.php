<?php

namespace App\Models\Concerns;

use App\Enums\IndicatorType\EtapaVigente;
use Illuminate\Database\Eloquent\Builder;

trait ScopesMetEtapaAutoevaluacion
{
    public function scopeEtapaAutoevaluacion(Builder $query): Builder
    {
        $etapa = EtapaVigente::Autoevaluacion->value;

        return $query->where(function (Builder $q) use ($etapa): void {
            $q->where('etapa', $etapa)
                ->orWhereNull('etapa')
                ->orWhere('etapa', '');
        });
    }
}
