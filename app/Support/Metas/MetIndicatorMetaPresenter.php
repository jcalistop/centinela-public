<?php

namespace App\Support\Metas;

use App\Enums\MetaOperator;

class MetIndicatorMetaPresenter
{
    /**
     * Meta como texto plano (operador + valor a 2 decimales + unidad), apta para atributos
     * `title` / `aria-label` donde no se puede usar el partial `indicator-meta-value`.
     */
    public static function plainLabel(
        float $value,
        MetaOperator|string|null $metaOperador = null,
        ?string $unidadMedida = null,
    ): string {
        $operador = $metaOperador instanceof MetaOperator
            ? $metaOperador->value
            : trim((string) $metaOperador);

        $label = ($operador !== '' ? $operador.' ' : '').number_format($value, 2);

        if ($unidadMedida === null || trim($unidadMedida) === '') {
            return $label;
        }

        return $unidadMedida === '%' ? $label.'%' : $label.' '.$unidadMedida;
    }

    /**
     * Número con hasta $maxDecimals decimales; omite ceros finales (10.00 → "10", 12.50 → "12.5").
     */
    public static function formatCompactNumber(float $value, int $maxDecimals = 2): string
    {
        $formatted = number_format($value, max(0, $maxDecimals), '.', '');

        if (! str_contains($formatted, '.')) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
