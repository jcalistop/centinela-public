<?php

declare(strict_types=1);

namespace App\Support\PlatformUsage;

use Illuminate\Http\Request;

class AreaClassifier
{
    public static function classify(Request $request): string
    {
        $path = strtolower(trim((string) $request->path(), '/'));
        $routeName = (string) ($request->route()?->getName() ?? '');

        if (self::isComges($path, $routeName)) {
            return 'compromisos';
        }

        if (str_starts_with($path, 'admin/adp')) {
            return 'adp';
        }

        if (preg_match('/^admin\/(metas-\d+)(\/|$)/', $path, $m) === 1) {
            $slug = $m[1];

            return match ($slug) {
                'metas-18834' => 'metas_18834',
                'metas-19664' => 'metas_19664',
                'metas-20707' => 'metas_20707',
                default => 'metas_other',
            };
        }

        return 'other';
    }

    private static function isComges(string $pathLower, string $routeName): bool
    {
        foreach ((array) config('centinela.usage_tracking.comges_path_prefixes', []) as $prefix) {
            $prefixLower = strtolower(trim((string) $prefix, '/'));
            if ($prefixLower !== '' && str_starts_with($pathLower, $prefixLower)) {
                return true;
            }
        }

        if ($routeName === '' || ! str_starts_with($routeName, 'voyager.')) {
            return false;
        }

        foreach ((array) config('centinela.usage_tracking.comges_route_name_contains', []) as $fragment) {
            $fragment = (string) $fragment;
            if ($fragment !== '' && str_contains($routeName, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
