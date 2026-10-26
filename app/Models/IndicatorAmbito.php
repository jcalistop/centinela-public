<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class IndicatorAmbito extends Model
{
    protected $fillable = [
        'nombre',
        'slug',
        'orden',
        'estado',
    ];

    protected $casts = [
        'orden' => 'integer',
        'estado' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (IndicatorAmbito $ambito): void {
            if ($ambito->slug === '' && $ambito->nombre !== '') {
                $ambito->slug = Str::slug($ambito->nombre);
            }
        });
    }

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('estado', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }

    /**
     * @return MorphToMany<MetIndicator, $this>
     */
    public function metIndicators(): MorphToMany
    {
        return $this->morphedByMany(MetIndicator::class, 'indicatable', 'indicator_ambito_assignments');
    }

    /**
     * @return MorphToMany<ComIndicator, $this>
     */
    public function comIndicators(): MorphToMany
    {
        return $this->morphedByMany(ComIndicator::class, 'indicatable', 'indicator_ambito_assignments');
    }
}
