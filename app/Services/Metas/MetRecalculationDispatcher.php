<?php

namespace App\Services\Metas;

use App\Enums\IndicatorType\EtapaVigente;
use App\Jobs\MetCalculateIndicatorScopeJob;
use App\Jobs\MetCalculateResultsJob;
use App\Models\IndicatorTypeSetting;
use Illuminate\Support\Facades\Cache;

class MetRecalculationDispatcher
{
    public function scheduleAutoevaluacionScope(MetRecalculationScope $scope): bool
    {
        if (! config('centinela.metas.recalculation_scope_enabled', true)) {
            return false;
        }

        if (! $this->autoevaluacionIsActiveForInstrument($scope->indicatorTypeId, $scope->dependencyId, $scope->yearId)) {
            return false;
        }

        $runner = app(MetAutoevaluacionCalculationRunner::class);
        $centerIds = $runner->resolveCenterIdsAffectedBySourceCenter(
            $scope->yearId,
            $scope->dependencyId,
            $scope->centerId,
            $scope->metIndicatorId,
            $scope->measurementUnitId,
        );

        $dispatched = false;

        foreach ($centerIds as $centerId) {
            $scoped = new MetRecalculationScope(
                yearId: $scope->yearId,
                dependencyId: $scope->dependencyId,
                indicatorTypeId: $scope->indicatorTypeId,
                centerId: $centerId,
                metIndicatorId: $scope->metIndicatorId,
                measurementUnitId: $scope->measurementUnitId,
            );

            if ($this->dispatchScopeJobIfNotDebounced($scoped)) {
                $dispatched = true;
            }
        }

        return $dispatched;
    }

    public function scheduleFullAutoevaluacion(int $yearId, int $dependencyId, int $indicatorTypeId): void
    {
        MetCalculateResultsJob::dispatchAutoevaluacion($yearId, $dependencyId, $indicatorTypeId);
    }

    private function dispatchScopeJobIfNotDebounced(MetRecalculationScope $scope): bool
    {
        $fingerprint = $scope->fingerprint();

        if (! $this->markDebounceWindow($fingerprint)) {
            return false;
        }

        MetCalculateIndicatorScopeJob::dispatch($scope);

        return true;
    }

    private function markDebounceWindow(string $fingerprint): bool
    {
        $cacheKey = 'metas_job_pending_'.$fingerprint;
        $seconds = (int) config('centinela.metas.recalculation_debounce_seconds', 90);

        if (Cache::has($cacheKey)) {
            return false;
        }

        Cache::put($cacheKey, true, now()->addSeconds($seconds));

        return true;
    }

    private function autoevaluacionIsActiveForInstrument(int $indicatorTypeId, int $dependencyId, int $yearId): bool
    {
        $setting = IndicatorTypeSetting::query()
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->where('year_id', $yearId)
            ->first();

        if (! $setting) {
            return false;
        }

        return in_array($setting->etapa_vigente, [
            EtapaVigente::Formulacion,
            EtapaVigente::Autoevaluacion,
        ], true);
    }
}
