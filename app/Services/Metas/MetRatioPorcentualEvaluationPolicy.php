<?php

namespace App\Services\Metas;

use App\Models\MetResult;
use App\Services\MetFormulaEvaluatorService;

class MetRatioPorcentualEvaluationPolicy
{
    private const ZERO_EPSILON = 0.0000001;

    public function __construct(
        private readonly MetFormulaEvaluatorService $formulaEvaluatorService,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $formulaTokens
     * @param  array<string, float|null>  $variables
     * @return array{ok: bool, result: float|null, message: string}
     */
    public function evaluate(
        string $periodo,
        float $numerador,
        float $denominador,
        array $formulaTokens,
        array $variables,
        int $decimals = 2,
        string $decimalesModo = 'redondear',
    ): array {
        $numIsZero = abs($numerador) < self::ZERO_EPSILON;
        $denIsZero = abs($denominador) < self::ZERO_EPSILON;

        if (! $numIsZero && $denIsZero) {
            return [
                'ok' => false,
                'result' => null,
                'message' => MetResult::MESSAGE_DIVISOR_CERO,
            ];
        }

        return $this->formulaEvaluatorService->evaluate(
            $formulaTokens,
            $variables,
            $decimals,
            $decimalesModo,
        );
    }
}
