<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sustituye placeholders `{{instance.*}}` en markdown in-app (SDD-339).
 */
final class InstanceIdentityMarkdown
{
    /**
     * @var list<string>
     */
    private const KNOWN_TOKENS = [
        'instance.ss_display_name',
        'instance.ss_url',
        'instance.unit_name',
    ];

    public static function apply(string $markdown): string
    {
        try {
            $map = [
                '{{instance.ss_display_name}}' => InstanceIdentity::displayName(),
                '{{instance.ss_url}}' => InstanceIdentity::url(),
                '{{instance.unit_name}}' => InstanceIdentity::unitName(),
            ];

            $result = str_replace(array_keys($map), array_values($map), $markdown);

            return (string) preg_replace_callback(
                '/\{\{(instance\.[a-z0-9_.]+)\}\}/i',
                static function (array $matches): string {
                    $token = strtolower($matches[1]);
                    if (! in_array($token, self::KNOWN_TOKENS, true)) {
                        Log::warning('InstanceIdentityMarkdown: placeholder desconocido', [
                            'token' => $matches[0],
                        ]);
                    }

                    return '';
                },
                $result
            );
        } catch (Throwable $e) {
            Log::error('InstanceIdentityMarkdown: fallo al sustituir placeholders', [
                'message' => $e->getMessage(),
            ]);

            return $markdown;
        }
    }
}
