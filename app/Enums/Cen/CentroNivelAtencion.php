<?php

namespace App\Enums\Cen;

enum CentroNivelAtencion: string
{
    case Primario = 'Primario';
    case Secundario = 'Secundario';
    case Terciario = 'Terciario';
    case Pendiente = 'Pendiente';
    case NoAplica = 'No Aplica';
    case Desconocido = 'Desconocido';
}
