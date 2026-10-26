<?php

namespace App\Enums\Corte;

enum CutoffTipo: string
{
    case Anual = 'ANUAL';
    case Semestral = 'SEMESTRAL';
    case Trimestral = 'TRIMESTRAL';
    case Mensual = 'MENSUAL';
}
