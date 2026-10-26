<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetUnitType extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'orden',
        'enabled',
    ];

    protected $casts = [
        'orden' => 'integer',
        'enabled' => 'boolean',
    ];

    public function measurementUnits(): HasMany
    {
        return $this->hasMany(MetMeasurementUnit::class, 'met_unit_type_id');
    }
}
