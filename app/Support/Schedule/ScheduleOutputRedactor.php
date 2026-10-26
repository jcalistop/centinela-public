<?php

namespace App\Support\Schedule;

/**
 * Redacta PII/secretos en salidas del scheduler antes de persistir en logs (SDD-223 DR-4).
 */
final class ScheduleOutputRedactor
{
    private const MAX_CHARS = 2000;

    public static function redact(string $output, int $maxChars = self::MAX_CHARS): string
    {
        $redacted = $output;

        $redacted = (string) preg_replace(
            '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
            '[email-redacted]',
            $redacted
        );

        $redacted = (string) preg_replace(
            '/(?i)(api[_-]?key|token|password|secret|authorization)\s*[:=]\s*\S+/',
            '$1=[redacted]',
            $redacted
        );

        $redacted = (string) preg_replace(
            '/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/',
            'Bearer [redacted]',
            $redacted
        );

        if (mb_strlen($redacted) > $maxChars) {
            return mb_substr($redacted, 0, $maxChars).'…[truncated]';
        }

        return $redacted;
    }
}
