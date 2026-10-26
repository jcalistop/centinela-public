<?php

namespace App\Enums;

enum MessagingAudience: string
{
    case AllUsers = 'all_users';
    case HealthServiceAll = 'health_service_all';
    case HealthServiceReferentes = 'health_service_referentes';
    case SpecificUsers = 'specific_users';

    public function label(): string
    {
        return match ($this) {
            self::AllUsers => 'Todos los usuarios registrados',
            self::HealthServiceAll => 'Todos los usuarios de un Servicio de Salud',
            self::HealthServiceReferentes => 'Solo referentes de un Servicio de Salud',
            self::SpecificUsers => 'Uno o más usuarios concretos',
        };
    }
}
