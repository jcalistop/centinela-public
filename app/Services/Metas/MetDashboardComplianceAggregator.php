<?php

namespace App\Services\Metas;

use App\Models\MetIndicator;
use Illuminate\Support\Collection;

/**
 * Agregación batch de cumplimiento Metas para el dashboard no admin (SDD-138).
 */
class MetDashboardComplianceAggregator
{
    public function __construct(
        private readonly MetIndicatorDesempenoByEstablishmentService $desempenoService,
    ) {}

    /**
     * @param  Collection<int, MetIndicator>  $indicators
     * @return array{
     *     promedio_by_indicator_id: array<int, float|null>,
     *     payload_by_indicator_id: array<int, array<string, mixed>>,
     *     compliance_by_center: array<int, array{percents: list<float>, indicator_count: int}>
     * }
     */
    public function aggregate(Collection $indicators, int $hieId, int $yearId): array
    {
        if ($indicators->isEmpty()) {
            return [
                'promedio_by_indicator_id' => [],
                'payload_by_indicator_id' => [],
                'compliance_by_center' => [],
            ];
        }

        $payloadByIndicatorId = $this->desempenoService->buildBatchForIndicators($indicators, $hieId, $yearId);

        $promedioByIndicatorId = [];
        $complianceByCenter = [];

        foreach ($payloadByIndicatorId as $indicatorId => $payload) {
            $promedioByIndicatorId[$indicatorId] = $payload['promedio_cumplimiento'] ?? null;

            foreach ($payload['rows'] as $row) {
                $centerId = (int) $row['cen_center_id'];
                if (! isset($complianceByCenter[$centerId])) {
                    $complianceByCenter[$centerId] = ['percents' => [], 'indicator_count' => 0];
                }

                $complianceByCenter[$centerId]['indicator_count']++;
                if ($row['cumplimiento'] !== null) {
                    $complianceByCenter[$centerId]['percents'][] = (float) $row['cumplimiento'];
                }
            }
        }

        return [
            'promedio_by_indicator_id' => $promedioByIndicatorId,
            'payload_by_indicator_id' => $payloadByIndicatorId,
            'compliance_by_center' => $complianceByCenter,
        ];
    }
}
