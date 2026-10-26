<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class AdpPreliminarySnapshotRow extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'adp_preliminary_snapshot_rows';

    protected $fillable = [
        'adp_preliminary_snapshot_id',
        'adp_indicator_id',
        'raw_result',
        'compliance_percent',
        'weight_percent',
        'error_message',
    ];

    protected $casts = [
        'raw_result' => 'decimal:6',
        'compliance_percent' => 'decimal:4',
        'weight_percent' => 'decimal:4',
    ];

    /**
     * @return BelongsTo<AdpPreliminarySnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(AdpPreliminarySnapshot::class, 'adp_preliminary_snapshot_id');
    }

    /**
     * @return BelongsTo<AdpIndicator, $this>
     */
    public function indicator(): BelongsTo
    {
        return $this->belongsTo(AdpIndicator::class, 'adp_indicator_id');
    }
}
