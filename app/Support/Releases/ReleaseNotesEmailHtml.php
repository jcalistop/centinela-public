<?php

declare(strict_types=1);

namespace App\Support\Releases;

use Illuminate\Support\Str;

/**
 * Convierte el body de GitHub Release a HTML seguro para clientes de correo.
 */
final class ReleaseNotesEmailHtml
{
    private const ALLOWED_SOURCE_TAGS = '<strong><b><em><i><a><br><p><ul><ol><li><h1><h2><h3><h4><h5><h6><code><pre><table><thead><tbody><tr><th><td><blockquote><hr><del><sup><sub>';

    private const ALLOWED_RENDERED_TAGS = '<p><br><strong><b><em><i><a><ul><ol><li><h1><h2><h3><h4><h5><h6><code><pre><table><thead><tbody><tr><th><td><blockquote><hr><del><sup><sub>';

    public static function render(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));
        if ($body === '') {
            return '';
        }

        $normalized = self::normalizeGithubBody($body);
        $html = Str::markdown($normalized);

        return self::sanitizeRenderedHtml($html);
    }

    private static function normalizeGithubBody(string $body): string
    {
        $body = preg_replace('/<\/?u>/i', '', $body) ?? $body;
        $body = preg_replace('/<\/?details>/i', '', $body) ?? $body;
        $body = preg_replace('/<\/?summary>/i', '', $body) ?? $body;
        $body = preg_replace('/<\/?mark>/i', '', $body) ?? $body;

        return strip_tags($body, self::ALLOWED_SOURCE_TAGS);
    }

    private static function sanitizeRenderedHtml(string $html): string
    {
        $html = strip_tags($html, self::ALLOWED_RENDERED_TAGS);

        return trim($html);
    }
}
