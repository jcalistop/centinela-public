<?php

declare(strict_types=1);

namespace App\Support\Deploy;

final class DeployExecutionMode
{
    public const AUTO = 'auto';

    public const MANUAL = 'manual';

    public const RELEASE_NOTIFY = 'release_notify';

    public const RELEASE_NOTIFY_TEST = 'release_notify_test';

    /**
     * @return list<string>
     */
    public static function deployModes(): array
    {
        return [self::AUTO, self::MANUAL];
    }

    public static function label(string $mode): string
    {
        return match ($mode) {
            self::AUTO => 'Deploy automático',
            self::MANUAL => 'Deploy manual',
            self::RELEASE_NOTIFY => 'Notificación CG',
            self::RELEASE_NOTIFY_TEST => 'Correo prueba',
            default => strtoupper($mode),
        };
    }

    public static function isNotificationMode(string $mode): bool
    {
        return in_array($mode, [self::RELEASE_NOTIFY, self::RELEASE_NOTIFY_TEST], true);
    }
}
