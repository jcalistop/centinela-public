<?php

namespace App\Services\Metas;

use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\MetIndicatorFormulaProfile;
use App\Models\MetIndicator;

class MetIndicatorFormulaProfileResolver
{
    public function resolve(MetIndicator $indicator): MetIndicatorFormulaProfile
    {
        if (! $indicator->relationLoaded('dataFields')) {
            $indicator->load('dataFields');
        }

        if (! $indicator->relationLoaded('formula')) {
            $indicator->load('formula');
        }

        $numeradorFields = $indicator->dataFields->where('componente', DataFieldComponente::Numerador);
        $denominadorFields = $indicator->dataFields->where('componente', DataFieldComponente::Denominador);

        if ($numeradorFields->count() !== 1 || $denominadorFields->count() !== 1) {
            return MetIndicatorFormulaProfile::Generico;
        }

        $expression = $indicator->formula?->expression;

        if (! is_array($expression) || $expression === []) {
            return MetIndicatorFormulaProfile::Generico;
        }

        $numeradorKey = (string) $numeradorFields->first()->variable_key;
        $denominadorKey = (string) $denominadorFields->first()->variable_key;

        if ($this->isRatioTimes100Expression($expression, $numeradorKey, $denominadorKey)) {
            return MetIndicatorFormulaProfile::RatioPorcentual11;
        }

        return MetIndicatorFormulaProfile::Generico;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     */
    public function isRatioTimes100Expression(array $tokens, string $numeradorKey, string $denominadorKey): bool
    {
        $normalized = $this->normalizeTokens($tokens);

        $expected = [
            ['type' => 'var', 'key' => $numeradorKey],
            ['type' => 'op', 'value' => '/'],
            ['type' => 'var', 'key' => $denominadorKey],
            ['type' => 'op', 'value' => '*'],
            ['type' => 'lit', 'value' => '100'],
        ];

        return $normalized === $expected;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTokens(array $tokens): array
    {
        $normalized = [];

        foreach ($tokens as $token) {
            $type = $token['type'] ?? null;

            if ($type === 'paren') {
                continue;
            }

            $normalized[] = match ($type) {
                'var' => [
                    'type' => 'var',
                    'key' => (string) ($token['key'] ?? ''),
                ],
                'op' => [
                    'type' => 'op',
                    'value' => (string) ($token['value'] ?? ''),
                ],
                'lit' => [
                    'type' => 'lit',
                    'value' => (string) ($token['value'] ?? ''),
                ],
                default => [],
            };
        }

        return array_values(array_filter($normalized));
    }
}
