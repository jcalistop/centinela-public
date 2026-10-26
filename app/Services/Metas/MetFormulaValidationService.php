<?php

namespace App\Services\Metas;

use App\Services\MetFormulaEvaluatorService;

class MetFormulaValidationService
{
    public const PHASE_STRUCTURE = 'structure';

    public const PHASE_REFERENCES = 'references';

    public const PHASE_DIVISION = 'division';

    private const MESSAGE_INVALID = 'La fórmula no es válida.';

    private const MESSAGE_DIVISION_ZERO = 'La fórmula contiene una división por cero inválida.';

    private const MESSAGE_UNBALANCED = 'La fórmula tiene paréntesis desbalanceados.';

    public function __construct(
        private readonly MetFormulaEvaluatorService $evaluator,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  list<string>|null  $fieldKeys  Claves `variable_key` definidas; null omite fase referencias.
     * @return array{ok: true}|array{ok: false, phase: string, message: string}
     */
    public function validate(array $tokens, ?array $fieldKeys = null): array
    {
        $structureError = $this->validateStructure($tokens);
        if ($structureError !== null) {
            return $this->fail(self::PHASE_STRUCTURE, $structureError);
        }

        if ($fieldKeys !== null) {
            $unknown = $this->undefinedVariableKeys($tokens, $fieldKeys);
            if ($unknown !== []) {
                return $this->fail(
                    self::PHASE_REFERENCES,
                    'La fórmula referencia variables no definidas: '.implode(', ', $unknown).'.',
                );
            }
        }

        $divisionError = $this->validateDivision($tokens);
        if ($divisionError !== null) {
            return $this->fail(self::PHASE_DIVISION, $divisionError);
        }

        return ['ok' => true];
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     */
    private function validateStructure(array $tokens): ?string
    {
        foreach ($tokens as $token) {
            $error = $this->validateToken($token);
            if ($error !== null) {
                return $error;
            }
        }

        $balance = 0;
        foreach ($tokens as $token) {
            if (($token['type'] ?? null) !== 'paren') {
                continue;
            }

            if (($token['value'] ?? null) === '(') {
                $balance++;
            }

            if (($token['value'] ?? null) === ')') {
                $balance--;
            }

            if ($balance < 0) {
                return self::MESSAGE_UNBALANCED;
            }
        }

        if ($balance !== 0) {
            return self::MESSAGE_UNBALANCED;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $token
     */
    private function validateToken(array $token): ?string
    {
        $type = $token['type'] ?? null;

        if ($type === 'fn') {
            $name = $token['name'] ?? null;
            if ($name !== 'min') {
                return 'La fórmula contiene una función no soportada.';
            }

            $args = $token['args'] ?? null;
            if (! is_array($args) || count($args) !== 2) {
                return 'La función min() debe tener exactamente dos argumentos.';
            }

            foreach ($args as $argIndex => $argTokens) {
                if (! is_array($argTokens)) {
                    return 'La función min() contiene argumentos inválidos.';
                }

                if ($argTokens === []) {
                    return 'El argumento '.($argIndex + 1).' de min() no puede estar vacío.';
                }

                $nested = $this->validate($argTokens);
                if (! ($nested['ok'] ?? false)) {
                    return $nested['message'] ?? self::MESSAGE_INVALID;
                }
            }

            return null;
        }

        if (! in_array($type, ['var', 'op', 'lit', 'paren'], true)) {
            return 'La fórmula contiene tokens no soportados.';
        }

        if ($type === 'var' && ! is_string($token['key'] ?? null)) {
            return 'La fórmula contiene variables inválidas.';
        }

        if (in_array($type, ['op', 'lit', 'paren'], true) && ! is_string($token['value'] ?? null)) {
            return 'La fórmula contiene literales u operadores inválidos.';
        }

        return null;
    }

    /**
     * Híbrido A+B: tautologías estáticas + múltiples muestras de placeholders.
     *
     * @param  array<int, array<string, mixed>>  $tokens
     */
    private function validateDivision(array $tokens): ?string
    {
        foreach ($this->divisionDenominators($tokens) as $denominator) {
            if ($this->isIdenticallyZero($denominator)) {
                return self::MESSAGE_DIVISION_ZERO;
            }
        }

        $variables = [];
        $this->collectVariableKeys($tokens, $variables);
        $keys = array_keys($variables);

        if ($keys === []) {
            return $this->evaluateOnce($tokens, []);
        }

        $sawOk = false;
        $onlyDivisorZero = true;
        $structuralMessage = null;

        foreach ($this->placeholderSampleSets($keys) as $placeholders) {
            $result = $this->evaluator->evaluate($tokens, $placeholders);

            if ($result['ok']) {
                $sawOk = true;
                break;
            }

            if (($result['message'] ?? null) === 'Error: divisor cero') {
                continue;
            }

            $onlyDivisorZero = false;
            $structuralMessage = $this->mapEvaluatorMessage($result['message'] ?? self::MESSAGE_INVALID);
            break;
        }

        if ($sawOk) {
            return null;
        }

        if ($structuralMessage !== null) {
            return $structuralMessage;
        }

        if ($onlyDivisorZero) {
            return self::MESSAGE_DIVISION_ZERO;
        }

        return self::MESSAGE_INVALID;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  array<string, float>  $placeholders
     */
    private function evaluateOnce(array $tokens, array $placeholders): ?string
    {
        $result = $this->evaluator->evaluate($tokens, $placeholders);

        if ($result['ok']) {
            return null;
        }

        return $this->mapEvaluatorMessage($result['message'] ?? self::MESSAGE_INVALID);
    }

    private function mapEvaluatorMessage(string $message): string
    {
        return match ($message) {
            'Fórmula inválida', 'N/D' => self::MESSAGE_INVALID,
            'Error: divisor cero' => self::MESSAGE_DIVISION_ZERO,
            default => $message,
        };
    }

    /**
     * Fallback documentado de muestras (capa A). No usar todas las variables en 1.0.
     *
     * @param  list<string>  $keys
     * @return list<array<string, float>>
     */
    private function placeholderSampleSets(array $keys): array
    {
        return [
            $this->sequentialPlaceholders($keys, 2.0, 1.0),
            $this->sequentialPlaceholders($keys, 10.0, 7.0),
            $this->sequentialPlaceholders($keys, 3.0, -1.5),
        ];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, float>
     */
    private function sequentialPlaceholders(array $keys, float $start, float $step): array
    {
        $placeholders = [];
        $value = $start;

        foreach ($keys as $key) {
            $placeholders[$key] = $value;
            $value += $step;
        }

        return $placeholders;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @return list<array<int, array<string, mixed>>>
     */
    private function divisionDenominators(array $tokens): array
    {
        $denominators = [];

        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if (($token['type'] ?? null) === 'fn' && is_array($token['args'] ?? null)) {
                foreach ($token['args'] as $argTokens) {
                    if (is_array($argTokens)) {
                        $denominators = array_merge($denominators, $this->divisionDenominators($argTokens));
                    }
                }
            }

            if (($token['type'] ?? null) !== 'op' || ($token['value'] ?? null) !== '/') {
                continue;
            }

            $primary = $this->extractPrimaryExpression($tokens, $i + 1);
            if ($primary !== []) {
                $denominators[] = $primary;
            }
        }

        return $denominators;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @return array<int, array<string, mixed>>
     */
    private function extractPrimaryExpression(array $tokens, int $start): array
    {
        if (! isset($tokens[$start])) {
            return [];
        }

        $token = $tokens[$start];
        $type = $token['type'] ?? null;

        if ($type === 'paren' && ($token['value'] ?? null) === '(') {
            $depth = 0;
            $slice = [];

            for ($i = $start, $count = count($tokens); $i < $count; $i++) {
                $current = $tokens[$i];
                $slice[] = $current;

                if (($current['type'] ?? null) === 'paren') {
                    if (($current['value'] ?? null) === '(') {
                        $depth++;
                    }
                    if (($current['value'] ?? null) === ')') {
                        $depth--;
                        if ($depth === 0) {
                            return $slice;
                        }
                    }
                }
            }

            return $slice;
        }

        if (in_array($type, ['var', 'lit', 'fn'], true)) {
            return [$token];
        }

        return [];
    }

    /**
     * Capa B — tautologías comunes (no exhaustivo algebraicamente).
     *
     * @param  array<int, array<string, mixed>>  $tokens
     */
    private function isIdenticallyZero(array $tokens): bool
    {
        $tokens = $this->unwrapOuterParens($tokens);

        if ($tokens === []) {
            return false;
        }

        if (count($tokens) === 1) {
            $token = $tokens[0];
            if (($token['type'] ?? null) === 'lit') {
                return $this->isNumericZero((string) ($token['value'] ?? ''));
            }

            return false;
        }

        if ($this->isSameVariableDifference($tokens)) {
            return true;
        }

        if ($this->isZeroTimesExpression($tokens)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @return array<int, array<string, mixed>>
     */
    private function unwrapOuterParens(array $tokens): array
    {
        while (
            count($tokens) >= 2
            && ($tokens[0]['type'] ?? null) === 'paren'
            && ($tokens[0]['value'] ?? null) === '('
            && ($tokens[count($tokens) - 1]['type'] ?? null) === 'paren'
            && ($tokens[count($tokens) - 1]['value'] ?? null) === ')'
            && $this->parensWrapEntireExpression($tokens)
        ) {
            $tokens = array_slice($tokens, 1, -1);
        }

        return $tokens;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     */
    private function parensWrapEntireExpression(array $tokens): bool
    {
        $depth = 0;

        foreach ($tokens as $index => $token) {
            if (($token['type'] ?? null) !== 'paren') {
                continue;
            }

            if (($token['value'] ?? null) === '(') {
                $depth++;
            }

            if (($token['value'] ?? null) === ')') {
                $depth--;
                if ($depth === 0 && $index !== count($tokens) - 1) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     */
    private function isSameVariableDifference(array $tokens): bool
    {
        if (count($tokens) !== 3) {
            return false;
        }

        if (($tokens[1]['type'] ?? null) !== 'op' || ($tokens[1]['value'] ?? null) !== '-') {
            return false;
        }

        if (($tokens[0]['type'] ?? null) !== 'var' || ($tokens[2]['type'] ?? null) !== 'var') {
            return false;
        }

        return ($tokens[0]['key'] ?? null) === ($tokens[2]['key'] ?? null)
            && is_string($tokens[0]['key'] ?? null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     */
    private function isZeroTimesExpression(array $tokens): bool
    {
        if (count($tokens) < 3) {
            return false;
        }

        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            if (($tokens[$i]['type'] ?? null) !== 'op' || ($tokens[$i]['value'] ?? null) !== '*') {
                continue;
            }

            $left = $i > 0 ? $tokens[$i - 1] : null;
            $right = $i + 1 < $count ? $tokens[$i + 1] : null;

            if ($left !== null && ($left['type'] ?? null) === 'lit' && $this->isNumericZero((string) ($left['value'] ?? ''))) {
                return true;
            }

            if ($right !== null && ($right['type'] ?? null) === 'lit' && $this->isNumericZero((string) ($right['value'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    private function isNumericZero(string $value): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        return abs((float) $value) < 1e-12;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  array<string, true>  $variables
     */
    private function collectVariableKeys(array $tokens, array &$variables): void
    {
        foreach ($tokens as $token) {
            if (($token['type'] ?? null) === 'var' && is_string($token['key'] ?? null)) {
                $variables[$token['key']] = true;
            }

            if (($token['type'] ?? null) === 'fn' && is_array($token['args'] ?? null)) {
                foreach ($token['args'] as $argTokens) {
                    if (is_array($argTokens)) {
                        $this->collectVariableKeys($argTokens, $variables);
                    }
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $tokens
     * @param  array<int, string>  $definedKeys
     * @return list<string>
     */
    private function undefinedVariableKeys(array $tokens, array $definedKeys): array
    {
        $referenced = [];
        $this->collectVariableKeys($tokens, $referenced);
        $defined = array_flip($definedKeys);

        return array_values(array_filter(
            array_keys($referenced),
            fn (string $key): bool => ! isset($defined[$key]),
        ));
    }

    /**
     * @return array{ok: false, phase: string, message: string}
     */
    private function fail(string $phase, string $message): array
    {
        return [
            'ok' => false,
            'phase' => $phase,
            'message' => $message,
        ];
    }
}
