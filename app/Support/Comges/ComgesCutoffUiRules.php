<?php

namespace App\Support\Comges;

use App\Enums\Corte\CutoffClave;
use App\Models\ComIndicatorCutoff;

/**
 * Reglas de presentación de medios verificables por corte en la ficha de indicador COMGES (año ≥ 2026).
 */
final class ComgesCutoffUiRules
{
    /** @var list<CutoffClave> */
    private const EVALUATION_DOCUMENT_CUTOFF_CLAVES = [
        CutoffClave::CorteI,
        CutoffClave::CorteII,
        CutoffClave::CorteUnico,
    ];

    /** @var list<string> */
    private const EVALUATION_DOCUMENT_NOMBRE_PATTERNS = [
        '1er monitoreo',
        '1° monitoreo',
        'primer monitoreo',
        '2° monitoreo',
        '2do monitoreo',
        'segundo monitoreo',
        'corte anual',
    ];

    public static function showsVerifiableEvaluationDocuments(ComIndicatorCutoff $indicatorCutoff): bool
    {
        $indicatorCutoff->loadMissing('cutoff');

        $clave = $indicatorCutoff->cutoff?->clave;

        if ($clave === CutoffClave::Apelacion) {
            return false;
        }

        if ($clave !== null && in_array($clave, self::EVALUATION_DOCUMENT_CUTOFF_CLAVES, true)) {
            return true;
        }

        return self::nombreCorteMatchesEvaluationDocument($indicatorCutoff->cutoff?->nombre_corte);
    }

    public static function showsVerifiableAppealDocuments(ComIndicatorCutoff $indicatorCutoff): bool
    {
        return ! (bool) $indicatorCutoff->evaluacion_local;
    }

    private static function nombreCorteMatchesEvaluationDocument(?string $nombreCorte): bool
    {
        $normalized = self::normalizeNombreCorte($nombreCorte);

        if ($normalized === '') {
            return false;
        }

        foreach (self::EVALUATION_DOCUMENT_NOMBRE_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeNombreCorte(?string $nombreCorte): string
    {
        $normalized = mb_strtolower(trim((string) $nombreCorte));

        return str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $normalized);
    }
}
