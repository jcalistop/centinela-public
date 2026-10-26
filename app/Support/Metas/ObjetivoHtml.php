<?php

declare(strict_types=1);

namespace App\Support\Metas;

/**
 * HTML allowlist para met_indicators.objetivo (SDD-231).
 */
final class ObjetivoHtml
{
    private const ALLOWED_TAGS = '<p><br><strong><b><u>';

    /**
     * null/'' → null; strip tags fuera de allowlist; trim; si queda vacío → null.
     */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);
        if ($html === '') {
            return null;
        }

        $cleaned = trim(strip_tags($html, self::ALLOWED_TAGS));
        if (self::plainTextFromHtml($cleaned) === '') {
            return null;
        }

        return $cleaned;
    }

    /**
     * true si $value === '' o no contiene '<'.
     */
    public static function isPlainText(string $value): bool
    {
        return $value === '' || ! str_contains($value, '<');
    }

    /**
     * HTML seguro para {!! !!}: plain → e()+nl2br; rich → re-sanitize.
     */
    public static function toSafeHtml(?string $stored): string
    {
        if ($stored === null || $stored === '') {
            return '';
        }

        if (self::isPlainText($stored)) {
            return nl2br(e($stored), false);
        }

        return self::sanitize($stored) ?? '';
    }

    /**
     * Extracto plain para mini-ficha; truncado a $max con '…' si corta.
     */
    public static function toPlainExcerpt(?string $stored, int $max = 220): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        $plain = self::plainTextFromHtml($stored);
        if ($plain === '') {
            return null;
        }

        if (mb_strlen($plain) <= $max) {
            return $plain;
        }

        return mb_substr($plain, 0, $max - 1).'…';
    }

    private static function plainTextFromHtml(string $value): string
    {
        $stripped = strip_tags($value);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $decoded) ?? $decoded);
    }
}
