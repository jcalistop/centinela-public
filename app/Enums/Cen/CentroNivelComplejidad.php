<?php

namespace App\Enums\Cen;

enum CentroNivelComplejidad: string
{
    case Alta = 'Alta';
    case Mediana = 'Mediana';
    case Baja = 'Baja';
    case NoAplica = 'No Aplica';
    case Pendiente = 'Pendiente';
    case Desconocido = 'Desconocido';
}
