<?php

namespace App\Livewire\MetasInstrument\Concerns;

use App\Models\MetIndicator;
use App\Services\MetCalculationService;
use App\Support\Metas\MetSemaforoThresholds;

trait ComputesMetStageCaptureMetrics
{
    /**
     * @param  array{meta_efectiva: string, numerador: string, denominador: string, ponderacion: string, decimales: int, unidad_medida: ?string}  $row
     * @return array{
     *     resultado: float|null,
     *     cumplimiento: float|null,
     *     cumpl_ponderado: float|null,
     *     cumplimiento_label_class: string
     * }
     */
    protected function metricsForEvaluationRow(?MetIndicator $indicator, array $row): array
    {
        $defaults = [
            'resultado' => null,
            'cumplimiento' => null,
            'cumpl_ponderado' => null,
            'cumplimiento_label_class' => 'default',
        ];

        if (! $indicator) {
            return $defaults;
        }

        $numerador = $row['numerador'] !== '' ? (float) $row['numerador'] : null;
        $denominador = $row['denominador'] !== '' ? (float) $row['denominador'] : null;
        $resultado = $this->resolveResultadoFromCapture($indicator, $numerador, $denominador);

        if ($resultado === null) {
            return $defaults;
        }

        $metaEfectiva = $row['meta_efectiva'] !== '' ? (float) $row['meta_efectiva'] : null;
        $calculationService = app(MetCalculationService::class);
        $cumplimiento = $calculationService->indicatorCompliancePercent($indicator, $resultado, $metaEfectiva);
        $cumplimientoEscalado = $calculationService->indicatorScaledCompliancePercent(
            $indicator,
            $resultado,
            $metaEfectiva,
        );

        $ponderacion = (float) $row['ponderacion'];
        $cumplPonderado = $cumplimientoEscalado !== null
            ? round(($cumplimientoEscalado * $ponderacion) / 100, 2)
            : null;

        return [
            'resultado' => $resultado,
            'cumplimiento' => $cumplimiento,
            'cumpl_ponderado' => $cumplPonderado,
            'cumplimiento_label_class' => MetSemaforoThresholds::labelClassForIndicatorCompliance(
                $cumplimiento,
                $indicator->umbral_superior !== null ? (float) $indicator->umbral_superior : null,
                $indicator->umbral_inferior !== null ? (float) $indicator->umbral_inferior : null,
            ),
        ];
    }

    protected function resolveResultadoFromCapture(
        MetIndicator $indicator,
        ?float $numerador,
        ?float $denominador,
    ): ?float {
        $evaluation = app(MetCalculationService::class)->evaluateAnnualCaptureQuotient(
            $indicator,
            $numerador,
            $denominador,
        );

        return $evaluation['ok'] ? $evaluation['result'] : null;
    }

    /**
     * Formato de captura (numerador/denominador) para UI e inputs: siempre 2 decimales.
     */
    protected function formatCaptureDecimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 2, '.', '');
    }
}
