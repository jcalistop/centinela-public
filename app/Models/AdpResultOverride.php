<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class AdpResultOverride extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'adp_result_overrides';

    protected $fillable = [
        'adp_final_result_id',
        'adp_indicator_id',
        'field_name',
        'previous_value',
        'new_value',
        'reason',
        'user_id',
    ];

    protected $casts = [
        'previous_value' => 'decimal:6',
        'new_value' => 'decimal:6',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
