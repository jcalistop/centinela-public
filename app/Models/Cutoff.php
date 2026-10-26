<?php

namespace App\Models;

use App\Enums\Corte\CutoffClave;
use App\Enums\Corte\CutoffTipo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cutoff extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $casts = [
        'clave' => CutoffClave::class,
        'tipo_corte' => CutoffTipo::class,
    ];

    public function scopeHabilitado(Builder $query): Builder
    {
        return $query->where('habilitado', true)->orderBy('orden');
    }
}
