<?php

namespace App\Exceptions;

use RuntimeException;

class IndicatorYearNotSetException extends RuntimeException
{
    public function __construct(string $message = 'Año indicador no configurado en sesión.')
    {
        parent::__construct($message);
    }
}
