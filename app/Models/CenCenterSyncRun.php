<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CenCenterSyncRun extends Model
{
    protected $guarded = [];

    protected $table = 'cen_center_sync_runs';

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(CenCenterSyncRunIssue::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(CenCenterSyncRunChange::class);
    }
}
