<?php

namespace App\Services\Metas;

use App\Enums\DecimalesModo;
use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\MetIndicatorFormulaProfile;
use App\Models\MetIndicator;
use App\Services\MetFormulaEvaluatorService;

class MetIndicatorEvaluationService
{
    public function __construct(
        private readonly MetIndicatorFormulaProfileResolver $profileResolver,
        private readonly MetRatioPorcentualEvaluationPolicy $ratioPorcentualEvaluationPolicy,
        private readonly MetFormulaEvaluatorService $formulaEvaluatorService,
    ) {}

    /**
     * @param  array<string, float|null>  $variables
     * @return array{ok: bool, result: float|null, message: string}
     */
    public function evaluate(
        MetIndicator $indicator,
        string $periodo,
        array $variables,
        ?float $numerador,
        ?float $denominador,
    ): array {
        if (! $indicator->formula || empty($indicator->formula->expression)) {
            return ['ok' => false, 'result' => null, 'message' => 'N/D'];
        }

        $decimals = $indicator->decimales ?? 2;
        $expression = $indicator->formula->expression;
        $decimalesModo = $indicator->decimales_modo ?? DecimalesModo::Redondear;
        $decimalesModoValue = $decimalesModo instanceof DecimalesModo ? $decimalesModo->value : (string) $decimalesModo;

        if (
            $this->profileResolver->resolve($indicator) === MetIndicatorFormulaProfile::RatioPorcentual11
            && $numerador !== null
            && $denominador !== null
        ) {
            return $this->ratioPorcentualEvaluationPolicy->evaluate(
                $periodo,
                $numerador,
                $denominador,
                $expression,
                $variables,
                $decimals,
                $decimalesModoValue,
            );
        }

        return $this->formulaEvaluatorService->evaluate(
            $expression,
            $variables,
            $decimals,
            $decimalesModo,
        );
    }

    /**
     * @return array<string, float|null>
     */
    public function variablesFromPrimaryComponents(
        MetIndicator $indicator,
        float $numerador,
        float $denominador,
    ): array {
        if (! $indicator->relationLoaded('dataFields')) {
            $indicator->load('dataFields');
        }

        $variables = [];

        foreach ($indicator->dataFields as $field) {
            if ($field->componente === DataFieldComponente::Numerador) {
                $variables[$field->variable_key] = $numerador;
            }

            if ($field->componente === DataFieldComponente::Denominador) {
                $variables[$field->variable_key] = $denominador;
            }
        }

        return $variables;
    }
}
