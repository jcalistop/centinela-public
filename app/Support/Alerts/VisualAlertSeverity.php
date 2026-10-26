<?php

namespace App\Support\Alerts;

enum VisualAlertSeverity: string
{
    case Warning = 'warning';
    case Success = 'success';
    case Info = 'info';
}
