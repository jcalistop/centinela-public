<?php

namespace App\Support\Configuration;

/**
 * Kill-switch del dashboard público guest (SDD-282).
 * Off por defecto: setting BD `module.public_dashboard_enabled`.
 */
final class PublicDashboardGate
{
    public const SETTING_KEY = 'module.public_dashboard_enabled';

    public static function enabled(): bool
    {
        return app(ConfigurationService::class)->isRadioOn(self::SETTING_KEY, false);
    }
}
