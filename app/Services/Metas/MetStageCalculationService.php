<?php

namespace App\Services\Metas;

use App\Enums\IndicatorType\EtapaVigente;
use App\Models\MetCenterInclusion;
use App\Models\MetFinalEvaluation;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetPreliminaryEvaluation;
use App\Services\MetCalculationService;

class MetStageCalculationService
{
    public function __construct(
        private readonly MetCalculationService $calculationService,
    ) {}

    public function calculateForStage(
        string $etapa,
        int $indicatorTypeId,
        int $yearId,
        int $dependencyId,
        ?int $centerId = null,
    ): void {
        if (! in_array($etapa, [EtapaVigente::Preliminar->value, EtapaVigente::Final->value], true)) {
            return;
        }

        $centerIds = $centerId !== null
            ? collect([$centerId])
            : MetCenterInclusion::query()
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $dependencyId)
                ->where('indicator_type_id', $indicatorTypeId)
                ->pluck('cen_center_id');

        foreach ($centerIds as $cenCenterId) {
            $configurations = MetIndicatorConfiguration::query()
                ->where('year_id', $yearId)
                ->where('cen_center_id', $cenCenterId)
                ->where('cen_hie_dependency_id', $dependencyId)
                ->where('seleccionado', true)
                ->where('aplica', true)
                ->whereNull('met_measurement_unit_id')
                ->whereHas('metIndicator', fn ($q) => $q->where('indicator_type_id', $indicatorTypeId))
                ->with('metIndicator')
                ->get();

            foreach ($configurations as $configuration) {
                $indicator = $configuration->metIndicator;
                if (! $indicator) {
                    continue;
                }

                $capture = $this->resolveCapture($etapa, $indicator->id, (int) $cenCenterId, $yearId, $dependencyId);
                if ($capture === null || ($capture['numerador'] === null && $capture['denominador'] === null)) {
                    continue;
                }

                $this->calculationService->calculateIndicatorFromAnnualCapture(
                    $indicator,
                    (int) $cenCenterId,
                    $yearId,
                    $dependencyId,
                    $etapa,
                    $capture['numerador'] !== null ? (float) $capture['numerador'] : null,
                    $capture['denominador'] !== null ? (float) $capture['denominador'] : null,
                );
            }

            $this->calculationService->calculateEstablishmentResult(
                (int) $cenCenterId,
                $yearId,
                $dependencyId,
                'ANUAL',
                $indicatorTypeId,
                $etapa,
            );
        }
    }

    /**
     * @return array{numerador: float|string|null, denominador: float|string|null}|null
     */
    private function resolveCapture(
        string $etapa,
        int $indicatorId,
        int $centerId,
        int $yearId,
        int $dependencyId,
    ): ?array {
        if ($etapa === EtapaVigente::Preliminar->value) {
            $row = MetPreliminaryEvaluation::query()
                ->where('met_indicator_id', $indicatorId)
                ->where('cen_center_id', $centerId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $dependencyId)
                ->first();

            return $row ? ['numerador' => $row->numerador, 'denominador' => $row->denominador] : null;
        }

        $row = MetFinalEvaluation::query()
            ->where('met_indicator_id', $indicatorId)
            ->where('cen_center_id', $centerId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->first();

        return $row ? ['numerador' => $row->numerador, 'denominador' => $row->denominador] : null;
    }
}
