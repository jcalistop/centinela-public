<?php

namespace App\Enums\Corte;

enum CutoffClave: string
{
    case CorteI = 'CORTE I';
    case CorteII = 'CORTE II';
    case CorteIII = 'CORTE III';
    case CorteIV = 'CORTE IV';
    case CorteUnico = 'CORTE UNICO';
    case Indicador = 'INDICADOR';
    case Requisito = 'REQUISITO';
    case Apelacion = 'APELACION';
}
