<?php

namespace App\Enums\Cen;

enum CentroTipoAtencion: string
{
    case Abierta = 'Abierta';
    case Cerrada = 'Cerrada';
    case AbiertaYCerrada = 'Abierta y Cerrada';
    case NoAplica = 'No Aplica';
    case Pendiente = 'Pendiente';
    case Desconocido = 'Desconocido';
}
