<?php

namespace App\Support\Configuration;

/**
 * Flag del piloto Filament settings (SDD-261).
 * Off por defecto: env/config o setting BD `module.filament_settings_enabled`.
 */
final class FilamentSettingsGate
{
    public const SETTING_KEY = 'module.filament_settings_enabled';

    /**
     * OR inclusivo: env/config O setting BD.
     * No mapear esta key en ConfigurationService::MAP_BD_TO_CONFIG — si no, el Off de BD
     * pisa el env y el flag local nunca enciende.
     */
    public static function enabled(): bool
    {
        if (filter_var(config('centinela.filament.settings_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return app(ConfigurationService::class)->isRadioOn(self::SETTING_KEY, false);
    }
}
