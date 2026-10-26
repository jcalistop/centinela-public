<?php

namespace App\Enums\Cen;

enum CenCenterProviderType: string
{
    case Case1 = 'Público';
    case Case2 = 'Pendiente';
    case Case3 = 'Privado';
    case Case4 = 'Fuerzas Armadas y de Orden';
    case Case5 = 'Institución Medicina Legal';
    case Case6 = 'No Aplica';
    case Desconocido = 'Desconocido';
}
