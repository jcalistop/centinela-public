<?php

namespace App\Enums\Metas;

enum EstadoConvenioEstablecimiento: string
{
    case Borrador = 'BORRADOR';
    case Enviado = 'ENVIADO';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Enviado => 'Enviado',
        };
    }
}
