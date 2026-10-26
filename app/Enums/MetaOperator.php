<?php

namespace App\Enums;

enum MetaOperator: string
{
    case MayorIgual = '>=';
    case Mayor = '>';
    case MenorIgual = '<=';
    case Menor = '<';
    case Igual = '=';
}
