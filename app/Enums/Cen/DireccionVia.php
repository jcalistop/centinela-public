<?php

namespace App\Enums\Cen;

enum DireccionVia: string
{
    case Avenida = 'Avenida';
    case Calle = 'Calle';
    case Camino = 'Camino';
    case Carretera = 'Carretera';
    case Otro = 'Otro';
    case Pasaje = 'Pasaje';
    case Sector = 'Sector';
    case Pendiente = 'Pendiente';
    case Desconocido = 'Desconocido';
}
