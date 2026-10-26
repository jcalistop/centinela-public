<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class AdpIndicatorFormula extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'adp_indicator_id',
        'expression',
        'description',
    ];

    protected $casts = [
        'expression' => 'array',
    ];

    /**
     * @return BelongsTo<AdpIndicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(AdpIndicator::class, 'adp_indicator_id');
    }
}
