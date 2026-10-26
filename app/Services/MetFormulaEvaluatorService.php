<?php

namespace App\Services;

use App\Enums\DecimalesModo;

class MetFormulaEvaluatorService
{
    /**
     * @param  array<int, array<string, string>>  $tokens
     * @param  array<string, float|int|null>  $variables
     * @return array{ok: bool, result: float|null, message: string}
     */
    public function evaluate(array $tokens, array $variables, int $decimals = 2, DecimalesModo|string $decimalesModo = DecimalesModo::Redondear): array
    {
        $result = $this->evaluateExpression($tokens, $variables);
        if (! $result['ok']) {
            return $result;
        }

        $modo = $decimalesModo instanceof DecimalesModo ? $decimalesModo : DecimalesModo::from($decimalesModo);

        return [
            'ok' => true,
            'result' => $this->applyDecimals($result['result'], $decimals, $modo),
            'message' => 'OK',
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $tokens
     * @param  array<string, float|int|null>  $variables
     * @return array{ok: bool, result: float|null, message: string}
     */
    private function evaluateExpression(array $tokens, array $variables): array
    {
        if (empty($tokens)) {
            return ['ok' => false, 'result' => null, 'message' => 'N/D'];
        }

        $rpn = $this->toRpn($tokens, $variables);
        if (! $rpn['ok']) {
            return [
                'ok' => false,
                'result' => null,
                'message' => $rpn['message'],
            ];
        }

        return $this->evaluateRpn($rpn['result']);
    }

    /**
     * @param  array<int, array<string, string>>  $tokens
     * @param  array<string, float|int|null>  $variables
     * @return array{ok: bool, result: array<int, float|string>|null, message: string}
     */
    private function toRpn(array $tokens, array $variables): array
    {
        $output = [];
        $operators = [];

        foreach ($tokens as $token) {
            $type = $token['type'] ?? null;

            if ($type === 'fn') {
                $fnResult = $this->evaluateFunctionToken($token, $variables);
                if (! $fnResult['ok']) {
                    return $fnResult;
                }

                $output[] = $fnResult['result'];

                continue;
            }

            if ($type === 'var') {
                $key = $token['key'] ?? '';

                if (! array_key_exists($key, $variables) || $variables[$key] === null) {
                    return ['ok' => false, 'result' => null, 'message' => 'N/D'];
                }

                $output[] = (float) $variables[$key];

                continue;
            }

            if ($type === 'lit') {
                $value = $token['value'] ?? null;
                if (! is_numeric($value)) {
                    return ['ok' => false, 'result' => null, 'message' => 'Fórmula inválida'];
                }

                $output[] = (float) $value;

                continue;
            }

            if ($type === 'op') {
                $op = $token['value'] ?? null;
                if (! in_array($op, ['+', '-', '*', '/'], true)) {
                    return ['ok' => false, 'result' => null, 'message' => 'Fórmula inválida'];
                }

                while (! empty($operators)) {
                    $top = end($operators);
                    if ($top === '(') {
                        break;
                    }

                    if ($this->precedence((string) $top) >= $this->precedence((string) $op)) {
                        $output[] = array_pop($operators);

                        continue;
                    }

                    break;
                }

                $operators[] = $op;

                continue;
            }

            if ($type === 'paren') {
                $paren = $token['value'] ?? null;
                if ($paren === '(') {
                    $operators[] = '(';

                    continue;
                }

                if ($paren === ')') {
                    $found = false;
                    while (! empty($operators)) {
                        $op = array_pop($operators);
                        if ($op === '(') {
                            $found = true;
                            break;
                        }
                        $output[] = $op;
                    }

                    if (! $found) {
                        return ['ok' => false, 'result' => null, 'message' => 'Fórmula inválida'];
                    }

                    continue;
                }
            }

            return ['ok' => false, 'result' => null, 'message' => 'Fórmula inválida'];
        }

        while (! empty($operators)) {
            $op = array_pop($operators);
            if ($op === '(' || $op === ')') {
                return ['ok' => false, 'result' => null, 'message' => 'Fórmula inválida'];
            }

            $output[] = $op;
        }

        return ['ok' => true, 'result' => $output, 'message' => 'OK'];
    }

    /**
     * @param  array<string, mixed>  $token
     * @param  array<string, float|int|null>  $variables
     * @return array{ok: bool, result: float|null, message: string}
     */
    private function evaluateFunctionToken(array $token, array $variables): array
    {
        $name = $token['name'] ?? null;
        $args = $token['args'] ?? null;

        if ($name !== 'min' || ! is_array($args) || count($args) !== 2) {
            return ['ok' => false, 'result' => null, 'message' => 'Fórmula inválida'];
        }

        $left = $this->evaluateExpression($args[0], $variables);
        if (! $left['ok']) {
            return $left;
        }

        $right = $this->evaluateExpression($args[1], $variables);
        if (! $right['ok']) {
            return $right;
        }

        return [
            'ok' => true,
            'result' => min($left['result'], $right['result']),
            'message' => 'OK',
        ];
    }

    /**
     * @param  array<int, float|string>  $rpn
     * @return array{ok: bool, result: float|null, message: string}
     */
    private function evaluateRpn(array $rpn): array
    {
        $stack = [];

        foreach ($rpn as $item) {
            if (is_float($item) || is_int($item)) {
                $stack[] = (float) $item;

                continue;
            }

            if (! in_array($item, ['+', '-', '*', '/'], true) || count($stack) < 2) {
                return ['ok' => false, 'result' => null, 'message' => 'Fórmula inválida'];
            }

            $b = array_pop($stack);
            $a = array_pop($stack);

            if ($item === '+') {
                $stack[] = $a + $b;
            } elseif ($item === '-') {
                $stack[] = $a - $b;
            } elseif ($item === '*') {
                $stack[] = $a * $b;
            } elseif ($item === '/') {
                if (abs($b) < 0.0000001) {
                    if (abs($a) < 0.0000001) {
                        $stack[] = 0.0;
                    } else {
                        return ['ok' => false, 'result' => null, 'message' => 'Error: divisor cero'];
                    }
                } else {
                    $stack[] = $a / $b;
                }
            }
        }

        if (count($stack) !== 1) {
            return ['ok' => false, 'result' => null, 'message' => 'Fórmula inválida'];
        }

        return ['ok' => true, 'result' => (float) $stack[0], 'message' => 'OK'];
    }

    private function precedence(string $operator): int
    {
        return in_array($operator, ['*', '/'], true) ? 2 : 1;
    }

    private function applyDecimals(float $value, int $decimals, DecimalesModo $mode): float
    {
        $decimals = max(0, min(10, $decimals));

        if ($mode === DecimalesModo::Truncar) {
            $factor = 10 ** $decimals;

            if ($value >= 0) {
                return floor($value * $factor) / $factor;
            }

            return ceil($value * $factor) / $factor;
        }

        return round($value, $decimals);
    }
}
