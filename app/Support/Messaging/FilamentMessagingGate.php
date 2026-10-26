<?php

namespace App\Support\Messaging;

use App\Support\Configuration\ConfigurationService;

/**
 * Flag de superficie — mensajería manual Filament (SDD-286).
 * Off por defecto: env/config o setting BD `module.filament_messaging_enabled`.
 */
final class FilamentMessagingGate
{
    public const SETTING_KEY = 'module.filament_messaging_enabled';

    /**
     * OR inclusivo: env/config O setting BD.
     * No mapear esta key en ConfigurationService::MAP_BD_TO_CONFIG.
     */
    public static function enabled(): bool
    {
        if (filter_var(config('centinela.filament.messaging_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return app(ConfigurationService::class)->isRadioOn(self::SETTING_KEY, false);
    }
}
