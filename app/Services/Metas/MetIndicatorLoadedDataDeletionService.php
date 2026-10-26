<?php

namespace App\Services\Metas;

use App\Models\MetIndicator;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Services\MetCalculationService;
use Illuminate\Support\Facades\DB;

class MetIndicatorLoadedDataDeletionService
{
    public function hasLoadedData(MetIndicator $indicator, int $dependencyId, int $yearId): bool
    {
        $fieldIds = $this->fieldIdsForIndicator($indicator);

        if ($fieldIds === []) {
            return false;
        }

        return MetMonthlyData::query()
            ->whereIn('met_data_field_id', $fieldIds)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->where('year_id', $yearId)
            ->exists();
    }

    public function hasLoadedDataInAnyDependency(MetIndicator $indicator, int $yearId): bool
    {
        $fieldIds = $this->fieldIdsForIndicator($indicator);

        if ($fieldIds === []) {
            return false;
        }

        return MetMonthlyData::query()
            ->withoutGlobalScope('belongs_to_health_service')
            ->whereIn('met_data_field_id', $fieldIds)
            ->where('year_id', $yearId)
            ->exists();
    }

    /**
     * @return array{monthly_deleted: int, results_deleted: int}
     */
    public function deleteForIndicatorScope(
        MetIndicator $indicator,
        int $indicatorTypeId,
        int $dependencyId,
        int $yearId,
    ): array {
        if ((int) $indicator->indicator_type_id !== $indicatorTypeId
            || (int) $indicator->year_id !== $yearId) {
            abort(404);
        }

        $fieldIds = $this->fieldIdsForIndicator($indicator);

        return DB::transaction(function () use ($indicator, $fieldIds, $dependencyId, $yearId): array {
            $monthlyDeleted = 0;
            $resultsDeleted = 0;

            if ($fieldIds !== []) {
                $monthlyDeleted = MetMonthlyData::query()
                    ->whereIn('met_data_field_id', $fieldIds)
                    ->where('cen_hie_dependency_id', $dependencyId)
                    ->where('year_id', $yearId)
                    ->delete();
            }

            $resultsDeleted = MetResult::query()
                ->where('met_indicator_id', $indicator->id)
                ->where('cen_hie_dependency_id', $dependencyId)
                ->where('year_id', $yearId)
                ->where('etapa', MetCalculationService::ETAPA_AUTOEVALUACION)
                ->delete();

            return [
                'monthly_deleted' => $monthlyDeleted,
                'results_deleted' => $resultsDeleted,
            ];
        });
    }

    /**
     * @return list<int>
     */
    private function fieldIdsForIndicator(MetIndicator $indicator): array
    {
        $indicator->loadMissing('dataFields');

        return $indicator->dataFields
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
