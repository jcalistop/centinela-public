<?php

namespace App\Enums\Cen;

enum CenCenterScope: string
{
    case Case1 = 'Establecimiento de Salud';
    case Case2 = 'Unidad de Atención';
    case Case3 = 'Programa de Atención';
    case Case4 = 'Establecimiento Medicina Legal';
    case Case5 = 'Establecimiento No de Salud';
    case Desconocido = 'Desconocido';
}
