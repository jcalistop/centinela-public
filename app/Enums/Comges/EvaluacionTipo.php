<?php

namespace App\Enums\Comges;

enum EvaluacionTipo: string
{
    case Autoevaluacion = 'AUTOEVALUACION';
    case Preliminar = 'PRELIMINAR';
    case Final = 'FINAL';
}
