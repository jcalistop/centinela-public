<?php

namespace App\Support\Dashboard;

use App\Models\PublicInfoPanel;
use Illuminate\Support\Facades\URL;

/**
 * Preview guest de paneles no publicados (SDD-293/295): URL firmada, gate de sesión y firma de assets cookie-less.
 */
final class PublicInfoPanelPreview
{
    public const SESSION_PREFIX = 'public_info_panel_preview.';

    public const ASSET_HMAC_PREFIX = 'public-info-panel-asset';

    public static function ttlDays(): int
    {
        return max(1, (int) config('centinela.public_info_panel_preview_ttl_days', 7));
    }

    public static function signedUrl(PublicInfoPanel $panel): string
    {
        return URL::temporarySignedRoute(
            'centinela.publico.panel.preview',
            now()->addDays(self::ttlDays()),
            ['slug' => $panel->slug],
        );
    }

    public static function mailtoHref(PublicInfoPanel $panel): string
    {
        return PublicInfoPanelShare::mailtoHref($panel);
    }

    public static function grant(string $slug, int $expiresAt): void
    {
        session([self::SESSION_PREFIX.$slug => $expiresAt]);
    }

    public static function allows(string $slug): bool
    {
        $expires = session(self::SESSION_PREFIX.$slug);
        if (! is_numeric($expires)) {
            return false;
        }

        if ((int) $expires < now()->getTimestamp()) {
            session()->forget(self::SESSION_PREFIX.$slug);

            return false;
        }

        return true;
    }

    public static function assetExpiresAt(): int
    {
        return now()->addDays(self::ttlDays())->getTimestamp();
    }

    public static function assetSignature(string $slug, int $expiresAt): string
    {
        return hash_hmac(
            'sha256',
            self::ASSET_HMAC_PREFIX.'|'.$slug.'|'.$expiresAt,
            (string) config('app.key'),
        );
    }

    public static function validAssetSignature(string $slug, mixed $signature, mixed $expiresAt): bool
    {
        if (! is_string($signature) || $signature === '' || ! is_numeric($expiresAt)) {
            return false;
        }

        $expires = (int) $expiresAt;
        if ($expires < now()->getTimestamp()) {
            return false;
        }

        return hash_equals(self::assetSignature($slug, $expires), $signature);
    }

    /**
     * @return array{expires: int, signature: string}
     */
    public static function assetToken(string $slug, ?int $expiresAt = null): array
    {
        $expires = $expiresAt ?? self::resolveAssetExpiresAt($slug);

        return [
            'expires' => $expires,
            'signature' => self::assetSignature($slug, $expires),
        ];
    }

    public static function signedAssetBaseHref(string $slug, ?int $expiresAt = null): string
    {
        $token = self::assetToken($slug, $expiresAt);

        return url('/storage/public-info-panels/'.$slug.'/p/'.$token['expires'].'/'.$token['signature'].'/r').'/';
    }

    public static function signedRelativeUrl(string $slug, string $path, ?int $expiresAt = null): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return self::signedAssetBaseHref($slug, $expiresAt).$path;
    }

    public static function signedTypedUrl(string $slug, string $asset, ?int $expiresAt = null): string
    {
        $token = self::assetToken($slug, $expiresAt);

        return url('/storage/public-info-panels/'.$slug.'/p/'.$token['expires'].'/'.$token['signature'].'/'.$asset);
    }

    public static function resolveAssetExpiresAt(string $slug): int
    {
        $fromSession = session(self::SESSION_PREFIX.$slug);
        $now = now()->getTimestamp();

        if (is_numeric($fromSession) && (int) $fromSession > $now) {
            return (int) $fromSession;
        }

        $fromRequest = request()->route('expires') ?? request()->query('expires');
        if (is_numeric($fromRequest) && (int) $fromRequest > $now) {
            return (int) $fromRequest;
        }

        return self::assetExpiresAt();
    }
}
