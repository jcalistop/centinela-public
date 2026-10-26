<?php

namespace App\Services\Adp;

use App\Http\Controllers\Voyager\ComSensitivityTableController;
use App\Models\ComSensitivityTable;
use Illuminate\Validation\ValidationException;

/**
 * Validación de rangos de sensibilidad ADP (misma lógica que COMGES / {@see ComSensitivityTableController}).
 */
final class AdpSensitivityRowsValidator
{
    /**
     * @param  array<int, array<string, mixed>>  $normalizedValores  Filas ya normalizadas con {@see ComSensitivityTable::normalizeValorRule()}.
     */
    public static function validate(array $normalizedValores): void
    {
        if ($normalizedValores === []) {
            throw ValidationException::withMessages([
                'valores' => 'Debe existir al menos una fila en la tabla de sensibilidad.',
            ]);
        }

        self::validateOverlappingRanges($normalizedValores);
        self::validateCoverageGaps($normalizedValores);
    }

    /**
     * @param  array<int, array<string, mixed>>  $normalizedValores
     */
    private static function validateOverlappingRanges(array $normalizedValores): void
    {
        $count = count($normalizedValores);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if (self::rangesOverlap($normalizedValores[$i], $normalizedValores[$j])) {
                    $rowA = $i + 1;
                    $rowB = $j + 1;

                    throw ValidationException::withMessages([
                        'valores' => "Existe solapamiento entre las filas {$rowA} y {$rowB} de la tabla de sensibilidad.",
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function rangesOverlap(array $a, array $b): bool
    {
        return ! self::isRangeBefore($a, $b) && ! self::isRangeBefore($b, $a);
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private static function isRangeBefore(array $left, array $right): bool
    {
        $leftMax = $left['valor_maximo'];
        $rightMin = $right['valor_minimo'];

        if ($leftMax === null || $rightMin === null) {
            return false;
        }

        if ($leftMax < $rightMin) {
            return true;
        }

        if ($leftMax > $rightMin) {
            return false;
        }

        return $left['operador_maximo'] === '<' || $right['operador_minimo'] === '>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $normalizedValores
     */
    private static function validateCoverageGaps(array $normalizedValores): void
    {
        $rows = [];

        foreach ($normalizedValores as $index => $row) {
            $rows[] = [
                ...$row,
                'row_number' => $index + 1,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $aMin = $a['valor_minimo'];
            $bMin = $b['valor_minimo'];

            if ($aMin === null && $bMin === null) {
                return 0;
            }

            if ($aMin === null) {
                return -1;
            }

            if ($bMin === null) {
                return 1;
            }

            return $aMin <=> $bMin;
        });

        $count = count($rows);

        for ($i = 0; $i < $count - 1; $i++) {
            $left = $rows[$i];
            $right = $rows[$i + 1];

            $leftMax = $left['valor_maximo'];
            $rightMin = $right['valor_minimo'];

            if ($leftMax === null || $rightMin === null) {
                continue;
            }

            $hasGap = $leftMax < $rightMin
                || (
                    $leftMax === $rightMin
                    && $left['operador_maximo'] === '<'
                    && $right['operador_minimo'] === '>'
                );

            if ($hasGap) {
                throw ValidationException::withMessages([
                    'valores' => "Existe un hueco de cobertura entre las filas {$left['row_number']} y {$right['row_number']}.",
                ]);
            }
        }
    }
}
