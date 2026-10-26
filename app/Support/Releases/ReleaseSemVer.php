<?php

declare(strict_types=1);

namespace App\Support\Releases;

final class ReleaseSemVer
{
    /**
     * @param  list<array{tag_name?: string, published_at?: ?string}>  $releases
     * @return list<array<string, mixed>>
     */
    public static function filterUpTo(?string $maxVersion, array $releases): array
    {
        if ($maxVersion === null || $maxVersion === '') {
            return [];
        }

        $max = self::parse($maxVersion);
        if ($max === null) {
            return [];
        }

        $out = [];
        foreach ($releases as $release) {
            $tag = trim((string) ($release['tag_name'] ?? ''));
            if ($tag === '') {
                continue;
            }

            $parsed = self::parse($tag);
            if ($parsed === null) {
                continue;
            }

            if (self::compare($parsed, $max) <= 0) {
                $out[] = $release;
            }
        }

        usort($out, static function (array $a, array $b): int {
            $ta = (string) ($a['published_at'] ?? '');
            $tb = (string) ($b['published_at'] ?? '');

            return strcmp($tb, $ta);
        });

        return array_values($out);
    }

    public static function compareTags(?string $a, ?string $b): int
    {
        $pa = self::parse((string) $a);
        $pb = self::parse((string) $b);

        if ($pa === null && $pb === null) {
            return 0;
        }

        if ($pa === null) {
            return -1;
        }

        if ($pb === null) {
            return 1;
        }

        return self::compare($pa, $pb);
    }

    public static function normalizeTag(string $tag): string
    {
        $tag = trim($tag);
        if ($tag === '') {
            return '';
        }

        if (! str_starts_with(strtolower($tag), 'v')) {
            return 'v'.$tag;
        }

        return $tag;
    }

    /**
     * @return array{major: int, minor: int, patch: int}|null
     */
    public static function parse(string $tag): ?array
    {
        $tag = self::normalizeTag($tag);
        if ($tag === '') {
            return null;
        }

        if (! preg_match('/^v(\d+)\.(\d+)\.(\d+)$/i', $tag, $m)) {
            return null;
        }

        return [
            'major' => (int) $m[1],
            'minor' => (int) $m[2],
            'patch' => (int) $m[3],
        ];
    }

    /**
     * @param  array{major: int, minor: int, patch: int}  $a
     * @param  array{major: int, minor: int, patch: int}  $b
     */
    private static function compare(array $a, array $b): int
    {
        foreach (['major', 'minor', 'patch'] as $key) {
            if ($a[$key] !== $b[$key]) {
                return $a[$key] <=> $b[$key];
            }
        }

        return 0;
    }
}
