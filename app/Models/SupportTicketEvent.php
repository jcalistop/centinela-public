<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketEvent extends Model
{
    public const TYPE_STATUS_CHANGED = 'status_changed';

    public const TYPE_ASSIGNMENT_CHANGED = 'assignment_changed';

    public const TYPE_GITHUB_LINKED = 'github_linked';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'support_ticket_id',
        'actor_user_id',
        'type',
        'is_internal',
        'comment',
        'meta',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_internal' => 'boolean',
        'meta' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
