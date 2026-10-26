<?php

namespace App\Enums\Cen;

enum CentroTipoSapu: string
{
    case Corto = 'Corto';
    case Verano = 'Verano';
    case Largo = 'Largo';
    case Avanzado = 'Avanzado';
    case Pendiente = 'Pendiente';
    case NoAplica = 'No Aplica';
    case Desconocido = 'Desconocido';
}
