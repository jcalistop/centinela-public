<?php

namespace App\Support\Configuration;

/**
 * Flag T2c — Filament Resource configurar-tipo-indicador (SDD-265).
 * Off por defecto: env/config o setting BD `module.filament_configurar_tipo_indicador_enabled`.
 */
final class FilamentConfigurarTipoIndicadorGate
{
    public const SETTING_KEY = 'module.filament_configurar_tipo_indicador_enabled';

    /**
     * OR inclusivo: env/config O setting BD.
     * No mapear esta key en ConfigurationService::MAP_BD_TO_CONFIG.
     */
    public static function enabled(): bool
    {
        if (filter_var(config('centinela.filament.configurar_tipo_indicador_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return app(ConfigurationService::class)->isRadioOn(self::SETTING_KEY, false);
    }
}
