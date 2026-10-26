<?php

namespace App\Support\Metas;

use App\Services\Metas\MetFormulaValidationService;

class MetFormulaTokenPresenter
{
    /**
     * @param  array<int, array<string, mixed>>  $tokens
     */
    public static function describe(array $tokens): string
    {
        return implode(' ', array_map(
            fn (array $token): string => self::describeToken($token),
            $tokens,
        ));
    }

    /**
     * Valida estructura, referencias (si se pasan claves) y división (híbrido A+B).
     * Delega en {@see MetFormulaValidationService}.
     *
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  list<string>|null  $fieldKeys
     */
    public static function validate(array $tokens, ?array $fieldKeys = null): ?string
    {
        $result = app(MetFormulaValidationService::class)->validate($tokens, $fieldKeys);

        return ($result['ok'] ?? false) ? null : ($result['message'] ?? 'La fórmula no es válida.');
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private static function describeToken(array $token): string
    {
        $type = $token['type'] ?? null;

        if ($type === 'fn') {
            $name = $token['name'] ?? '';
            $args = $token['args'] ?? [];

            if (! is_array($args) || count($args) !== 2) {
                return '?';
            }

            $left = is_array($args[0]) ? self::describe($args[0]) : '?';
            $right = is_array($args[1]) ? self::describe($args[1]) : '?';

            return sprintf('%s(%s, %s)', $name, $left, $right);
        }

        return match ($type) {
            'var' => (string) ($token['key'] ?? '?'),
            'op', 'lit', 'paren' => (string) ($token['value'] ?? '?'),
            default => '?',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     */
    public static function referencesVariable(array $tokens, string $variableKey): bool
    {
        foreach ($tokens as $token) {
            if (($token['type'] ?? null) === 'var' && ($token['key'] ?? null) === $variableKey) {
                return true;
            }

            if (($token['type'] ?? null) === 'fn' && is_array($token['args'] ?? null)) {
                foreach ($token['args'] as $argTokens) {
                    if (is_array($argTokens) && self::referencesVariable($argTokens, $variableKey)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  array<int, string>  $definedKeys
     * @return list<string>
     */
    public static function undefinedVariableKeys(array $tokens, array $definedKeys): array
    {
        $referenced = [];
        self::collectVariableKeys($tokens, $referenced);

        $defined = array_flip($definedKeys);

        return array_values(array_filter(
            array_keys($referenced),
            fn (string $key): bool => ! isset($defined[$key]),
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  array<string, true>  $variables
     */
    private static function collectVariableKeys(array $tokens, array &$variables): void
    {
        foreach ($tokens as $token) {
            if (($token['type'] ?? null) === 'var' && is_string($token['key'] ?? null)) {
                $variables[$token['key']] = true;
            }

            if (($token['type'] ?? null) === 'fn' && is_array($token['args'] ?? null)) {
                foreach ($token['args'] as $argTokens) {
                    if (is_array($argTokens)) {
                        self::collectVariableKeys($argTokens, $variables);
                    }
                }
            }
        }
    }
}
