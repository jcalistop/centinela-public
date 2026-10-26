<?php

namespace App\Support;

use App\Services\Releases\DeployedReleaseState;

class CentinelaSession
{
    public static function bootstrap(): void
    {
        $yearContext = app(YearContext::class);
        $tagName = self::resolveDeployedVersionLabel();

        session([
            'release_tag' => $tagName,
        ]);

        $yearContext->setCurrent($yearContext->resolveDefaultYear());
    }

    public static function resolveDeployedVersionLabel(): string
    {
        try {
            $deployed = app(DeployedReleaseState::class)->displayVersion();
            if ($deployed !== '') {
                return $deployed;
            }
        } catch (\Throwable) {
            // Mantener fallback si BD no está disponible aún.
        }

        $cfgVersion = trim((string) (config('app.version') ?? ''));

        return $cfgVersion !== '' ? $cfgVersion : 'N/D';
    }
}
