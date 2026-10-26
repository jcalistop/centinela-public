<?php

namespace App\Helpers;

use App\Support\Comges\ComgesYearMode;
use App\Support\YearContext;

class Helper
{
    public static function showCurrentYear()
    {
        return app(YearContext::class)->current();
    }

    /**
     * COMGES: true si el año activo usa escala de puntaje (0–4) en lugar de porcentaje.
     */
    public static function comgesUsesScoreScale(): bool
    {
        return ComgesYearMode::currentUsesScoreScale();
    }

    private const SEMAFORO_STYLE = "style='border-radius:0.9rem'";

    public static function muestra_semaforo(float|int|string|null $valor, int $limite_amarillo): string
    {
        $s = self::SEMAFORO_STYLE;
        if (is_null($valor)) {
            return "<div class='semaforo nulo' $s>S/I</div>";
        }

        $numericValue = (float) $valor;
        $displayValue = number_format($numericValue, 0, '.', '');

        if ($numericValue >= 100.0) {
            return "<div class='semaforo green' $s>$displayValue%</div>";
        } elseif ($numericValue >= $limite_amarillo && $numericValue < 100.0) {
            return "<div class='semaforo orange' $s>$displayValue%</div>";
        } elseif ($numericValue >= 0.0 && $numericValue < $limite_amarillo) {
            return "<div class='semaforo red' $s>$displayValue%</div>";
        } else {
            return "<div class='semaforo error' $s>$displayValue%</div>";
        }
    }

    public static function muestra_puntaje(float|int|string|null $valor): string
    {
        $s = self::SEMAFORO_STYLE;
        if (is_null($valor)) {
            return "<div class='semaforo nulo' $s>S/I</div>";
        }

        $numericValue = (float) $valor;
        $displayValue = number_format($numericValue, 2, '.', '');

        if ($numericValue == 4.0) {
            return "<div class='semaforo green' $s>$displayValue</div>";
        } elseif ($numericValue < 4.0 && $numericValue >= 3.0) {
            return "<div class='semaforo orange' $s>$displayValue</div>";
        } elseif ($numericValue < 3.0) {
            return "<div class='semaforo red' $s>$displayValue</div>";
        } else {
            return "<div class='semaforo error' $s>$displayValue</div>";
        }
    }
}
