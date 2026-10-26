<?php

namespace App\Support\Configuration;

/**
 * Flag T2a — Filament Resource instrumentos-evaluacion (SDD-262).
 * Off por defecto: env/config o setting BD `module.filament_instrumentos_evaluacion_enabled`.
 */
final class FilamentInstrumentosEvaluacionGate
{
    public const SETTING_KEY = 'module.filament_instrumentos_evaluacion_enabled';

    /**
     * OR inclusivo: env/config O setting BD.
     * No mapear esta key en ConfigurationService::MAP_BD_TO_CONFIG.
     */
    public static function enabled(): bool
    {
        if (filter_var(config('centinela.filament.instrumentos_evaluacion_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return app(ConfigurationService::class)->isRadioOn(self::SETTING_KEY, false);
    }
}
