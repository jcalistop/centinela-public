<?php

namespace App\Support\Metas;

/**
 * Formato numérico chileno para export/import XLSX Metas (coma decimal, sin separador de miles).
 */
final class MetExcelDecimal
{
    public const MAX_DECIMALS = 4;

    public static function formatForExport(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $numeric = (float) $value;
        $formatted = rtrim(rtrim(number_format($numeric, self::MAX_DECIMALS, ',', ''), '0'), ',');

        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * @return array{valid: bool, value: ?float}
     */
    public static function tryParseImported(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return ['valid' => true, 'value' => null];
        }

        if (is_int($raw) || is_float($raw)) {
            return ['valid' => true, 'value' => (float) $raw];
        }

        $normalized = self::normalizeNumericString((string) $raw);

        if ($normalized === null || ! is_numeric($normalized)) {
            return ['valid' => false, 'value' => null];
        }

        return ['valid' => true, 'value' => (float) $normalized];
    }

    private static function normalizeNumericString(string $raw): ?string
    {
        $value = trim($raw);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);

            return $value;
        }

        if (preg_match('/^-?\d+\.\d+$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^-?\d+$/', $value) === 1) {
            return $value;
        }

        return null;
    }
}
