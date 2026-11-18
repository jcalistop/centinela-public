<?php

namespace App\Support\Storage;

/**
 * Mitiga path traversal y rutas absolutas en rutas relativas al disco `private`.
 */
final class RelativePathGuard
{
    public static function isSafe(string $relativePath): bool
    {
        if ($relativePath === '') {
            return false;
        }

        if (str_contains($relativePath, '..') || str_contains($relativePath, "\0")) {
            return false;
        }

        if (str_starts_with($relativePath, '/') || str_starts_with($relativePath, '\\')) {
            return false;
        }

        if (preg_match('#^[a-zA-Z]:[/\\\\]#', $relativePath) === 1) {
            return false;
        }

        return true;
    }

    public static function assertSafe(string $relativePath): void
    {
        if (! self::isSafe($relativePath)) {
            abort(403);
        }
    }
}
