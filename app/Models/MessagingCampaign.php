<?php

namespace App\Models;

use App\Enums\MessagingAudience;
use App\Enums\MessagingCampaignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessagingCampaign extends Model
{
    protected $fillable = [
        'sender_id',
        'audience',
        'cen_hie_dependency_id',
        'subject',
        'body_html',
        'body_text',
        'status',
        'recipients_total',
        'recipients_sent',
        'recipients_failed',
    ];

    protected $casts = [
        'audience' => MessagingAudience::class,
        'status' => MessagingCampaignStatus::class,
        'recipients_total' => 'integer',
        'recipients_sent' => 'integer',
        'recipients_failed' => 'integer',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function healthService(): BelongsTo
    {
        return $this->belongsTo(CenHieDependency::class, 'cen_hie_dependency_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MessagingCampaignRecipient::class);
    }
}
