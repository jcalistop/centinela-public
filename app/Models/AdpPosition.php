<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class AdpPosition extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
    ];

    /**
     * @return HasMany<AdpPositionAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AdpPositionAssignment::class, 'adp_position_id');
    }
}
