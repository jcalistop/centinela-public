<?php

namespace App\Enums;

enum MessagingRecipientStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
