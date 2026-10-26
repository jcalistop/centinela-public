<?php

namespace App\Support\Comges;

use App\Models\Year;
use App\Support\YearContext;

/**
 * Reglas de presentación COMGES según el año del instrumento en sesión.
 * A partir de 2025 las orientaciones técnicas usan puntaje (0–4); años anteriores, porcentaje (0–100).
 */
final class ComgesYearMode
{
    public const SCORE_SCALE_FIRST_YEAR = 2025;

    /** Año en que los cortes usan claves de monitoreo (CORTE I/II/ÚNICO) en lugar de INDICADOR/REQUISITO. */
    public const MONITORING_CUTOFF_FIRST_YEAR = 2026;

    /** Año en que el corte Apelación no muestra sensibilidad ni evaluaciones por acción. */
    public const APPELACION_ACTIONS_MINIMAL_UI_YEAR = 2025;

    public static function usesScoreScale(?Year $year = null): bool
    {
        $year ??= app(YearContext::class)->current();

        return $year instanceof Year
            && (int) $year->ano >= self::SCORE_SCALE_FIRST_YEAR;
    }

    public static function currentUsesScoreScale(): bool
    {
        return self::usesScoreScale();
    }

    public static function usesMonitoringCutoffUi(?Year $year = null): bool
    {
        $year ??= app(YearContext::class)->current();

        return $year instanceof Year
            && (int) $year->ano >= self::MONITORING_CUTOFF_FIRST_YEAR;
    }

    public static function currentUsesMonitoringCutoffUi(): bool
    {
        return self::usesMonitoringCutoffUi();
    }

    /**
     * En el instrumento 2025, las acciones del corte Apelación solo exponen carga de apelaciones.
     */
    public static function apelacionActionsHideSensitivityAndEvaluations(?Year $year = null): bool
    {
        $year ??= app(YearContext::class)->current();

        return $year instanceof Year
            && (int) $year->ano === self::APPELACION_ACTIONS_MINIMAL_UI_YEAR;
    }
}
