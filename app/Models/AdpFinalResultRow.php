<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class AdpFinalResultRow extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'adp_final_result_rows';

    protected $fillable = [
        'adp_final_result_id',
        'adp_indicator_id',
        'preliminary_compliance_percent',
        'final_compliance_percent',
        'raw_result',
        'is_overridden',
    ];

    protected $casts = [
        'preliminary_compliance_percent' => 'decimal:4',
        'final_compliance_percent' => 'decimal:4',
        'raw_result' => 'decimal:6',
        'is_overridden' => 'boolean',
    ];

    /**
     * @return BelongsTo<AdpFinalResult, $this>
     */
    public function finalResult(): BelongsTo
    {
        return $this->belongsTo(AdpFinalResult::class, 'adp_final_result_id');
    }

    /**
     * @return BelongsTo<AdpIndicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(AdpIndicator::class, 'adp_indicator_id');
    }
}
