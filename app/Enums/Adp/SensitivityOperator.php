<?php

namespace App\Enums\Adp;

enum SensitivityOperator: string
{
    case MayorIgual = '>=';
    case Mayor = '>';
    case MenorIgual = '<=';
    case Menor = '<';
}
