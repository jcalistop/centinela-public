<?php

namespace App\Models;

use App\Enums\Cen\CenCenterSyncRunChangeAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenCenterSyncRunChange extends Model
{
    protected $guarded = [];

    protected $table = 'cen_center_sync_run_changes';

    protected $casts = [
        'action' => CenCenterSyncRunChangeAction::class,
        'context' => 'array',
    ];

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(CenCenterSyncRun::class, 'cen_center_sync_run_id');
    }
}
