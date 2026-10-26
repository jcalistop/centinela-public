<?php

namespace App\Enums\Cen;

enum CentroTieneUrgencia: string
{
    case Si = 'Sí';
    case No = 'No';
    case NoAplica = 'No Aplica';
    case Pendiente = 'Pendiente';
    case Desconocido = 'Desconocido';
}
