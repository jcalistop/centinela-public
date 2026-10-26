<?php

namespace App\Enums\Cen;

enum CenCenterAdmDependency: string
{
    case Case1 = 'Servicio de Salud';
    case Case2 = 'Fuerzas Armadas y de Orden (FFAA)';
    case Case3 = 'Municipal';
    case Case4 = 'Privado';
    case Case5 = 'SEREMI de Salud';
    case Case6 = 'Ministerio de Justicia';
    case Case7 = 'Gendamería';
    case Case8 = 'Otra Institución';
    case Case9 = 'Delegados';
    case Case10 = 'Corporación Municipal';
    case Case11 = 'Ministerio del Trabajo y Previsión Social,';
    case Case12 = 'MINSAL';
    case Desconocido = 'Desconocido';
}
