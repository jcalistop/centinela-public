<?php

namespace App\Enums;

enum MessagingCampaignStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Encolada',
            self::Sending => 'Enviando',
            self::Completed => 'Completada',
            self::Failed => 'Fallida',
        };
    }
}
