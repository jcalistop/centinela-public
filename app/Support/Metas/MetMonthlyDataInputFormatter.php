<?php

namespace App\Support\Metas;

/**
 * Formato de valores de carga mensual para inputs HTML (punto decimal, sin separador de miles).
 */
final class MetMonthlyDataInputFormatter
{
    public const MAX_DECIMALS = 4;

    public static function formatForInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        $numeric = (float) $value;

        if (abs($numeric - round($numeric)) < 1e-9) {
            return (string) (int) round($numeric);
        }

        $formatted = rtrim(rtrim(number_format($numeric, self::MAX_DECIMALS, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
