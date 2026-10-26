<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRequestLog extends Model
{
    protected $fillable = [
        'request_id',
        'user_id',
        'method',
        'path',
        'query_hash',
        'status_code',
        'ip',
        'latency_ms',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'status_code' => 'integer',
        'latency_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
