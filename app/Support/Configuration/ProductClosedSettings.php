<?php

namespace App\Support\Configuration;

/**
 * Keys de Settings ocultas en el producto cerrado (SDD-308).
 * Filas BD y bridges quedan; no se muestran en SettingsManager / ManageSystemSettings.
 */
final class ProductClosedSettings
{
    /**
     * @var list<string>
     */
    public const EXACT_KEYS = [
        'admin.voyager_compass_enabled',
        'module.chat_ia_enabled',
        'module.user_manual_enabled',
        'module.whatsapp_enabled',
        'notifications.whatsapp_alert_to',
    ];

    public static function isHiddenKey(string $key): bool
    {
        if (str_starts_with($key, 'module.filament_')) {
            return true;
        }

        return in_array($key, self::EXACT_KEYS, true);
    }
}
