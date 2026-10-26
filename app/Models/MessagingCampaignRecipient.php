<?php

namespace App\Models;

use App\Enums\MessagingRecipientStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessagingCampaignRecipient extends Model
{
    protected $fillable = [
        'messaging_campaign_id',
        'user_id',
        'email',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'status' => MessagingRecipientStatus::class,
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MessagingCampaign::class, 'messaging_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
