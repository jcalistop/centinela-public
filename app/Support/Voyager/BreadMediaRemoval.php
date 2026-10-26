<?php

namespace App\Support\Voyager;

use Throwable;

final class BreadMediaRemoval
{
    /**
     * Valor JSON persistible tras quitar todos los adjuntos de un campo `file` BREAD.
     * Evita violar columnas NOT NULL (`archivos`, `file`, etc.).
     *
     * @param  array<int, mixed>  $fieldData
     */
    public static function serializedFileFieldAfterRemoval(array $fieldData): string
    {
        return json_encode(empty($fieldData) ? [] : array_values($fieldData));
    }

    public static function httpStatusCodeFromException(Throwable $e): int
    {
        $code = (int) $e->getCode();

        return ($code >= 400 && $code < 600) ? $code : 500;
    }
}
