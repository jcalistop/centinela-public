<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use Illuminate\Support\Facades\Cache;
use TCG\Voyager\Facades\Voyager;

/**
 * URL resuelta de admin.bg_image para #gradient_bg y --imagen-de-fondo (cacheada).
 */
final class AdminBackgroundImage
{
    private const CACHE_KEY = 'centinela.admin.bg_image.url';

    public static function cachedUrl(): string
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): string => self::resolveUrl());
    }

    public static function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function resolveUrl(): string
    {
        $stored = Voyager::setting('admin.bg_image');

        return Voyager::image($stored, voyager_asset('images/bg.jpg'));
    }
}
