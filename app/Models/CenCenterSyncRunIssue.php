<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenCenterSyncRunIssue extends Model
{
    protected $guarded = [];

    protected $table = 'cen_center_sync_run_issues';

    protected $casts = [
        'context' => 'array',
        'promoted_at' => 'datetime',
    ];

    public function isPromoted(): bool
    {
        return $this->promoted_at !== null;
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(CenCenterSyncRun::class, 'cen_center_sync_run_id');
    }
}
