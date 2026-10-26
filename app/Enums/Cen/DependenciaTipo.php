<?php

namespace App\Enums\Cen;

enum DependenciaTipo: string
{
    case Servicio = 'SERVICIO';
    case Seremi = 'SEREMI';
    case Otro = 'OTRO';
}
