<?php

namespace App\Support\Carbon;

use Carbon\Carbon;

/**
 * Convierte formatos strftime de Voyager BREAD a formatos PHP date (Carbon 3).
 *
 * Carbon 3 eliminó {@see Carbon::formatLocalized()}; Voyager sigue usando
 * tokens strftime (p. ej. `%d-%m-%Y %H:%M` en data_rows.details.format).
 */
final class StrftimeFormatConverter
{
    /**
     * @var array<string, string>
     */
    private const MAP = [
        '%Y' => 'Y',
        '%y' => 'y',
        '%m' => 'm',
        '%d' => 'd',
        '%e' => 'j',
        '%H' => 'H',
        '%I' => 'h',
        '%M' => 'i',
        '%S' => 's',
        '%B' => 'F',
        '%b' => 'M',
        '%A' => 'l',
        '%a' => 'D',
        '%p' => 'A',
        '%P' => 'a',
    ];

    public static function toPhpFormat(string $strftimeFormat): string
    {
        $protected = str_replace('%%', "\0PERCENT\0", $strftimeFormat);
        $converted = strtr($protected, self::MAP);

        return str_replace("\0PERCENT\0", '%', $converted);
    }
}
