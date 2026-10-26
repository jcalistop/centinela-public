<?php

namespace App\Enums\Cen;

enum CentroPertenenciaSnss: string
{
    case Perteneciente = 'Perteneciente';
    case NoPerteneciente = 'No Perteneciente';
    case NoAplica = 'No Aplica';
    case Desconocido = 'Desconocido';
}
