<?php

namespace App\Support\Configuration;

/**
 * Flag T2b — Filament Resource establecimientos (SDD-264).
 * Off por defecto: env/config o setting BD `module.filament_establecimientos_enabled`.
 */
final class FilamentEstablecimientosGate
{
    public const SETTING_KEY = 'module.filament_establecimientos_enabled';

    /**
     * OR inclusivo: env/config O setting BD.
     * No mapear esta key en ConfigurationService::MAP_BD_TO_CONFIG.
     */
    public static function enabled(): bool
    {
        if (filter_var(config('centinela.filament.establecimientos_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return app(ConfigurationService::class)->isRadioOn(self::SETTING_KEY, false);
    }
}
