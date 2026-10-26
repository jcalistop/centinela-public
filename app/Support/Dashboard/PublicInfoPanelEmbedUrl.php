<?php

namespace App\Support\Dashboard;

/**
 * Validación de URLs de embed externo para paneles públicos (SDD-290).
 */
final class PublicInfoPanelEmbedUrl
{
    public static function isAllowed(?string $url): bool
    {
        $url = trim((string) $url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        $path = (string) ($parts['path'] ?? '/');

        foreach (config('centinela.public_panel_embed.allowed_hosts', []) as $rule) {
            if (! is_string($rule) || $rule === '') {
                continue;
            }

            if (self::hostMatches($host, $rule)) {
                if ($rule === '*.arcgis.com' && ! self::arcgisPublicPath($path)) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    private static function hostMatches(string $host, string $rule): bool
    {
        $rule = strtolower($rule);

        if (str_starts_with($rule, '*.')) {
            $suffix = substr($rule, 1); // .arcgis.com

            return $host === substr($rule, 2) || str_ends_with($host, $suffix);
        }

        return $host === $rule;
    }

    private static function arcgisPublicPath(string $path): bool
    {
        $path = strtolower($path);

        return str_contains($path, '/apps/dashboards')
            || str_contains($path, '/apps/experiencebuilder')
            || str_contains($path, '/experience/')
            || str_contains($path, '/home/item.html');
    }
}
