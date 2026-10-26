<?php

namespace App\Services\Adp;

use App\Models\AdpAgreementPeriod;
use App\Models\AdpAgreementPeriodResult;
use App\Models\AdpIndicator;
use App\Models\AdpIndicatorResult;
use App\Services\MetFormulaEvaluatorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdpCalculationService
{
    public function __construct(
        private readonly MetFormulaEvaluatorService $formulaEvaluator,
    ) {}

    /**
     * Recalcula y persiste resultados por indicador y resultado global del periodo (idempotente).
     */
    public function recalculatePeriod(AdpAgreementPeriod $period): AdpAgreementPeriodResult
    {
        AdpPeriodWeightsValidator::assertPeriodReadyForCalculation($period);

        $period->loadMissing(['agreement', 'objectives.indicators.formula', 'objectives.indicators.target', 'objectives.indicators.sensitivityTable.rows']);

        $indicators = AdpIndicator::query()
            ->whereHas('objective', function ($q) use ($period): void {
                $q->where('adp_agreement_period_id', $period->id);
            })
            ->with(['formula', 'target', 'sensitivityTable.rows'])
            ->orderBy('id')
            ->get();

        $nIndicators = $indicators->count();
        $nCalculated = 0;
        $nFailed = 0;
        $weighted = 0.0;
        $details = [];

        $now = Carbon::now();

        /** @var AdpAgreementPeriodResult|null $periodResult */
        $periodResult = null;

        DB::transaction(function () use ($period, $indicators, $nIndicators, $now, &$nCalculated, &$nFailed, &$weighted, &$details, &$periodResult): void {
            foreach ($indicators as $indicator) {
                $target = $indicator->target;
                $weight = $target !== null ? (float) $target->weight_percent : 0.0;

                $eval = $this->evaluateIndicator($indicator, $period);

                AdpIndicatorResult::query()->updateOrCreate(
                    [
                        'adp_indicator_id' => $indicator->id,
                        'adp_agreement_period_id' => $period->id,
                    ],
                    [
                        'raw_result' => $eval['raw'],
                        'compliance_percent' => $eval['compliance'],
                        'error_message' => $eval['error'],
                        'calculated_at' => $now,
                    ]
                );

                if ($eval['error'] !== null) {
                    $nFailed++;
                    $details[] = [
                        'adp_indicator_id' => $indicator->id,
                        'clave' => $indicator->clave,
                        'error' => $eval['error'],
                    ];

                    continue;
                }

                if ($eval['compliance'] === null) {
                    $nFailed++;
                    $details[] = [
                        'adp_indicator_id' => $indicator->id,
                        'clave' => $indicator->clave,
                        'error' => 'Cumplimiento no disponible.',
                    ];

                    continue;
                }

                $nCalculated++;
                $weighted += ($weight / 100.0) * (float) $eval['compliance'];
            }

            AdpAgreementPeriodResult::query()->updateOrCreate(
                ['adp_agreement_period_id' => $period->id],
                [
                    'weighted_global_percent' => $nCalculated > 0 ? round($weighted, 4) : null,
                    'n_indicators' => $nIndicators,
                    'n_calculated' => $nCalculated,
                    'n_failed' => $nFailed,
                    'details_json' => $details !== [] ? $details : null,
                    'calculated_at' => $now,
                ]
            );

            $periodResult = AdpAgreementPeriodResult::query()
                ->where('adp_agreement_period_id', $period->id)
                ->first();
        });

        return $periodResult ?? AdpAgreementPeriodResult::query()
            ->where('adp_agreement_period_id', $period->id)
            ->firstOrFail();
    }

    /**
     * @return array{raw: float|null, compliance: float|null, error: string|null}
     */
    private function evaluateIndicator(AdpIndicator $indicator, AdpAgreementPeriod $period): array
    {
        $variables = AdpMonthlyVariableAggregator::buildVariables($indicator, $period);

        $evaluation = ['ok' => false, 'result' => null, 'message' => 'N/D'];

        if ($indicator->formula !== null && ! empty($indicator->formula->expression)) {
            $evaluation = $this->formulaEvaluator->evaluate(
                $indicator->formula->expression,
                $variables,
                (int) ($indicator->decimales ?? 2),
                $indicator->decimales_modo ?? 'redondear'
            );
        } else {
            $sum = $variables['A'] ?? null;
            if ($sum !== null) {
                $evaluation = ['ok' => true, 'result' => (float) $sum, 'message' => 'OK'];
            }
        }

        $raw = $evaluation['ok'] ? $evaluation['result'] : null;
        $formulaErr = $evaluation['ok'] ? null : ($evaluation['message'] ?? 'Fórmula inválida o variables faltantes');

        if ($raw === null) {
            return [
                'raw' => null,
                'compliance' => null,
                'error' => $formulaErr,
            ];
        }

        $method = (string) ($indicator->calculation_method ?? 'percent');

        return match ($method) {
            'sensitivity' => $this->evaluateSensitivity($indicator, (float) $raw),
            'dichotomic' => $this->evaluateDichotomic($indicator, (float) $raw),
            default => $this->evaluatePercent($indicator, (float) $raw),
        };
    }

    /**
     * @return array{raw: float|null, compliance: float|null, error: string|null}
     */
    private function evaluatePercent(AdpIndicator $indicator, float $raw): array
    {
        $meta = $this->resolveMeta($indicator);
        $linear = $this->linearPercentFromMeta($raw, $meta);

        if ($linear === null) {
            return [
                'raw' => $raw,
                'compliance' => null,
                'error' => 'Meta no definida o no positiva para cálculo porcentual.',
            ];
        }

        $scaled = $this->resolveScaledCompliance($linear, $indicator);

        return ['raw' => $raw, 'compliance' => $scaled, 'error' => null];
    }

    /**
     * @return array{raw: float|null, compliance: float|null, error: string|null}
     */
    private function evaluateDichotomic(AdpIndicator $indicator, float $raw): array
    {
        $meta = $this->resolveMeta($indicator);
        $op = $this->resolveMetaOperator($indicator);

        if ($meta === null || $op === null) {
            return [
                'raw' => $raw,
                'compliance' => null,
                'error' => 'Meta u operador no definidos para modo dicotómico.',
            ];
        }

        $cumple = match ($op) {
            '>=' => $raw >= $meta,
            '>' => $raw > $meta,
            '<=' => $raw <= $meta,
            '<' => $raw < $meta,
            '=' => abs($raw - $meta) < 0.000001,
            default => null,
        };

        if ($cumple === null) {
            return ['raw' => $raw, 'compliance' => null, 'error' => 'Operador de meta no soportado.'];
        }

        return ['raw' => $raw, 'compliance' => $cumple ? 100.0 : 0.0, 'error' => null];
    }

    /**
     * @return array{raw: float|null, compliance: float|null, error: string|null}
     */
    private function evaluateSensitivity(AdpIndicator $indicator, float $raw): array
    {
        $table = $indicator->sensitivityTable;
        if ($table === null || $table->rows->isEmpty()) {
            return [
                'raw' => $raw,
                'compliance' => null,
                'error' => 'Indicador en modo sensibilidad sin tabla configurada.',
            ];
        }

        try {
            $table->validateConfiguredRanges();
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'Tabla de sensibilidad inválida.';

            return [
                'raw' => $raw,
                'compliance' => null,
                'error' => (string) $msg,
            ];
        }

        $mapped = $table->resolveCumplimiento($raw);
        if ($mapped === null) {
            return [
                'raw' => $raw,
                'compliance' => null,
                'error' => 'El resultado numérico no cae en ningún rango de la tabla de sensibilidad.',
            ];
        }

        return ['raw' => $raw, 'compliance' => $mapped, 'error' => null];
    }

    private function resolveMeta(AdpIndicator $indicator): ?float
    {
        $target = $indicator->target;

        if ($target !== null && $target->target_value !== null) {
            return (float) $target->target_value;
        }

        if ($indicator->meta_valor !== null) {
            return (float) $indicator->meta_valor;
        }

        return null;
    }

    private function resolveMetaOperator(AdpIndicator $indicator): ?string
    {
        $target = $indicator->target;

        if ($target !== null && $target->target_operador !== null) {
            return $target->target_operador->value;
        }

        return $indicator->meta_operador?->value;
    }

    private function linearPercentFromMeta(float $raw, ?float $meta): ?float
    {
        if ($meta === null || $meta <= 0) {
            return null;
        }

        return ($raw / $meta) * 100.0;
    }

    private function resolveScaledCompliance(float $cumplimiento, AdpIndicator $indicator): float
    {
        $umbralInferior = $indicator->umbral_inferior !== null ? (float) $indicator->umbral_inferior : 0.0;
        $umbralSuperior = $indicator->umbral_superior !== null ? (float) $indicator->umbral_superior : 100.0;

        if ($cumplimiento < $umbralInferior) {
            return 0.0;
        }

        if ($cumplimiento >= $umbralSuperior) {
            return 100.0;
        }

        return $cumplimiento;
    }
}
