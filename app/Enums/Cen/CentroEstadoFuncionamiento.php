<?php

namespace App\Enums\Cen;

enum CentroEstadoFuncionamiento: string
{
    case VigenteEnOperacionHabitual = 'Vigente en Operación Habitual';
    case VigenteEnOperacionTransitoria = 'Vigente en Operación Transitoria';
    case Cerrado = 'Cerrado';
    case Desconocido = 'Desconocido';

    public function isClosed(): bool
    {
        return $this === self::Cerrado;
    }

    public static function isClosedValue(?string $value): bool
    {
        return self::tryFrom((string) $value)?->isClosed() ?? false;
    }
}
