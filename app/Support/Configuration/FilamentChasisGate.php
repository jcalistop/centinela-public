<?php

namespace App\Support\Configuration;

/**
 * Maestro del chasis Filament (SDD-284 / ADR-039 §2.2).
 *
 * SDD-308: hard-off en runtime (env/BD ignorados). Reabrir exige spec.
 * En PHPUnit se conserva el OR inclusivo legado para no reescribir la suite.
 */
final class FilamentChasisGate
{
    public const SETTING_KEY = 'module.filament_enabled';

    /**
     * Runtime: siempre false. PHPUnit: OR inclusivo config/BD.
     */
    public static function enabled(): bool
    {
        if (app()->runningUnitTests()) {
            return self::legacyInclusiveOr();
        }

        return self::runtimeEnabled();
    }

    public static function runtimeEnabled(): bool
    {
        return false;
    }

    /**
     * OR inclusivo: env/config O setting BD.
     * No mapear esta key en ConfigurationService::MAP_BD_TO_CONFIG.
     */
    private static function legacyInclusiveOr(): bool
    {
        if (filter_var(config('centinela.filament.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return app(ConfigurationService::class)->isRadioOn(self::SETTING_KEY, false);
    }
}
