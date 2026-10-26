<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class AdpObjective extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'adp_agreement_period_id',
        'clave',
        'nombre',
        'descripcion',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    /**
     * @return BelongsTo<AdpAgreementPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AdpAgreementPeriod::class, 'adp_agreement_period_id');
    }

    /**
     * @return HasMany<AdpIndicator, $this>
     */
    public function indicators(): HasMany
    {
        return $this->hasMany(AdpIndicator::class, 'adp_objective_id')->orderBy('orden');
    }
}
