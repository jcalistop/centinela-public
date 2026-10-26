<?php

namespace App\Enums\IndicatorType;

enum TipoEvaluacion: string
{
    case Anual = 'ANUAL';
    case Semestral = 'SEMESTRAL';
    case Trimestral = 'TRIMESTRAL';
    case Mensual = 'MENSUAL';
    case NoDefinido = 'NO DEFINIDO';
}
