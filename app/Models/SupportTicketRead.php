<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketRead extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'support_ticket_id',
        'user_id',
        'last_read_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'last_read_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
