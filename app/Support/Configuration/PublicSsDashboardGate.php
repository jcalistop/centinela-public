<?php

namespace App\Support\Configuration;

/**
 * Flag de vitrina SS en el dashboard público (SDD-282).
 * Off por defecto: setting BD `module.public_ss_dashboard_enabled`.
 * Solo aplica cuando {@see PublicDashboardGate} está On.
 */
final class PublicSsDashboardGate
{
    public const SETTING_KEY = 'module.public_ss_dashboard_enabled';

    public static function enabled(): bool
    {
        return app(ConfigurationService::class)->isRadioOn(self::SETTING_KEY, false);
    }
}
