<?php

namespace App\Services\Adp;

use App\Models\ComSensitivityTable;

/**
 * Coincidencia de un valor numérico con una fila de sensibilidad (misma semántica que {@see ComSensitivityTable}).
 */
final class AdpSensitivityBoundary
{
    /**
     * @param  array<string, mixed>  $rule  Regla normalizada (valor_minimo, operador_minimo, valor_maximo, operador_maximo, cumplimiento).
     */
    public static function matches(float $resultado, array $rule): bool
    {
        return self::matchesMin($resultado, $rule['valor_minimo'] ?? null, (string) ($rule['operador_minimo'] ?? '>='))
            && self::matchesMax($resultado, $rule['valor_maximo'] ?? null, (string) ($rule['operador_maximo'] ?? '<'));
    }

    private static function matchesMin(float $resultado, ?float $minValue, string $operator): bool
    {
        if ($minValue === null) {
            return true;
        }

        return $operator === '>'
            ? $resultado > $minValue
            : $resultado >= $minValue;
    }

    private static function matchesMax(float $resultado, ?float $maxValue, string $operator): bool
    {
        if ($maxValue === null) {
            return true;
        }

        return $operator === '<='
            ? $resultado <= $maxValue
            : $resultado < $maxValue;
    }
}
