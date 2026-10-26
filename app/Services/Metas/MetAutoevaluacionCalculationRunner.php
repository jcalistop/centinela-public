<?php

namespace App\Services\Metas;

use App\Models\CenCenter;
use App\Models\MetEstablishmentResult;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetResult;
use App\Services\MetCalculationService;
use App\Services\MetDataAggregatorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetAutoevaluacionCalculationRunner
{
    public function __construct(
        private readonly MetDataAggregatorService $aggregatorService,
        private readonly MetCalculationService $calculationService,
        private readonly MetEstablishmentIndicatorLoadability $loadability,
        private readonly MetIndicatorCalculationDataScopeService $dataScopeService,
    ) {}

    /**
     * @return array{processed: int, failed: int}
     */
    public function runForIndicatorAtCenter(
        int $yearId,
        int $dependencyId,
        int $centerId,
        int $metIndicatorId,
        ?int $measurementUnitId = null,
    ): array {
        $indicator = MetIndicator::query()
            ->whereKey($metIndicatorId)
            ->with(['indicatorType', 'formula', 'dataFields'])
            ->first();

        if (! $indicator || ! $indicator->indicatorType) {
            return ['processed' => 0, 'failed' => 0];
        }

        $center = CenCenter::query()->find($centerId);

        if (! $center) {
            return ['processed' => 0, 'failed' => 0];
        }

        $configQuery = MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $metIndicatorId)
            ->where('cen_center_id', $centerId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $dependencyId);

        if ($measurementUnitId !== null) {
            $configQuery->where('met_measurement_unit_id', $measurementUnitId);
        } else {
            $configQuery->whereNull('met_measurement_unit_id');
        }

        $config = $configQuery->first();

        if (! $config || ! $config->aplica) {
            return ['processed' => 0, 'failed' => 0];
        }

        $adosadoIds = $this->dataScopeService->additionalDataCenterIds(
            $indicator,
            $centerId,
            $config,
            $yearId,
            $dependencyId,
            $measurementUnitId,
        );

        /** @var array<int, array<string, bool>> $periodsToAggregateByType */
        $periodsToAggregateByType = [];
        $processed = 0;
        $failed = 0;

        try {
            if ($indicator->indicatorType->usesInternalUnitMeasurement() && $measurementUnitId !== null) {
                $internalConfigs = collect([$config]);
                $this->runInternalUnitIndicatorCalculations(
                    $internalConfigs,
                    $center,
                    $adosadoIds,
                    $yearId,
                    $dependencyId,
                    $periodsToAggregateByType,
                );
                $processed++;
            } elseif (! $indicator->indicatorType->usesInternalUnitMeasurement()) {
                $establishmentConfigs = collect([$config]);
                $this->runEstablishmentIndicatorCalculations(
                    $establishmentConfigs,
                    $center,
                    $adosadoIds,
                    $yearId,
                    $dependencyId,
                    $periodsToAggregateByType,
                );
                $processed++;
            }
        } catch (Throwable $e) {
            $failed++;
            Log::warning('[metas.calculate.scope] indicator calculation failed', [
                'year_id' => $yearId,
                'cen_hie_dependency_id' => $dependencyId,
                'center_id' => $centerId,
                'met_indicator_id' => $metIndicatorId,
                'exception' => $e->getMessage(),
            ]);
        }

        foreach ($periodsToAggregateByType as $typeId => $periodMap) {
            foreach (array_keys($periodMap) as $periodo) {
                try {
                    $this->calculationService->calculateEstablishmentResult(
                        $centerId,
                        $yearId,
                        $dependencyId,
                        $periodo,
                        (int) $typeId,
                    );
                    $processed++;
                } catch (Throwable $e) {
                    $failed++;
                    Log::warning('[metas.calculate.scope] establishment result failed', [
                        'year_id' => $yearId,
                        'cen_hie_dependency_id' => $dependencyId,
                        'center_id' => $centerId,
                        'indicator_type_id' => (int) $typeId,
                        'periodo' => $periodo,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            if ($periodMap !== []) {
                try {
                    $this->calculationService->calculateEstablishmentResult(
                        $centerId,
                        $yearId,
                        $dependencyId,
                        'ANUAL',
                        (int) $typeId,
                    );
                    $processed++;
                } catch (Throwable $e) {
                    $failed++;
                    Log::warning('[metas.calculate.scope] establishment result failed', [
                        'year_id' => $yearId,
                        'cen_hie_dependency_id' => $dependencyId,
                        'center_id' => $centerId,
                        'indicator_type_id' => (int) $typeId,
                        'periodo' => 'ANUAL',
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            $validEstablishmentPeriods = array_values(array_unique([
                ...array_keys($periodMap),
                'ANUAL',
            ]));

            if ($validEstablishmentPeriods !== []) {
                MetEstablishmentResult::query()
                    ->where('cen_center_id', $centerId)
                    ->where('year_id', $yearId)
                    ->where('cen_hie_dependency_id', $dependencyId)
                    ->where('indicator_type_id', (int) $typeId)
                    ->where('etapa', MetCalculationService::ETAPA_AUTOEVALUACION)
                    ->whereNotIn('periodo', $validEstablishmentPeriods)
                    ->delete();
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    /**
     * Centros que deben recalcularse cuando cambian datos en {@see $sourceCenterId} (inverso de agrupa_ss).
     *
     * @return array<int, int>
     */
    public function resolveCenterIdsAffectedBySourceCenter(
        int $yearId,
        int $dependencyId,
        int $sourceCenterId,
        int $metIndicatorId,
        ?int $measurementUnitId,
    ): array {
        $centerIds = [$sourceCenterId];

        $agrupaAtOtherCenters = MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $metIndicatorId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->where('agrupa_ss', true)
            ->where('aplica', true)
            ->where('cen_center_id', '!=', $sourceCenterId)
            ->when(
                $measurementUnitId !== null,
                fn ($q) => $q->where('met_measurement_unit_id', $measurementUnitId),
                fn ($q) => $q->whereNull('met_measurement_unit_id'),
            )
            ->pluck('cen_center_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $indicatorTypeId = (int) MetIndicator::query()
            ->whereKey($metIndicatorId)
            ->value('indicator_type_id');

        foreach ($agrupaAtOtherCenters as $motherCenterId) {
            $otherMotherIds = MetIndicatorConfiguration::query()
                ->where('met_indicator_id', $metIndicatorId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $dependencyId)
                ->where('aplica', true)
                ->where('cen_center_id', '!=', $motherCenterId)
                ->when(
                    $measurementUnitId !== null,
                    fn ($q) => $q->where('met_measurement_unit_id', $measurementUnitId),
                    fn ($q) => $q->whereNull('met_measurement_unit_id'),
                )
                ->pluck('cen_center_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $allowed = $this->loadability
                ->allowedEstablishmentCenterIdsIncludingAdosados(
                    $otherMotherIds,
                    $yearId,
                    $dependencyId,
                    $indicatorTypeId,
                )
                ->map(fn ($id) => (int) $id);

            if ($allowed->contains($sourceCenterId)) {
                $centerIds[] = $motherCenterId;
            }
        }

        return array_values(array_unique($centerIds));
    }

    /**
     * @param  Collection<int, MetIndicatorConfiguration>  $establishmentConfigurations
     * @param  array<int, array<string, bool>>  $periodsToAggregateByType
     */
    public function runEstablishmentIndicatorCalculations(
        Collection $establishmentConfigurations,
        CenCenter $center,
        array $adosadoIds,
        int $yearId,
        int $dependencyId,
        array &$periodsToAggregateByType,
    ): void {
        $indicators = $establishmentConfigurations
            ->pluck('metIndicator')
            ->filter()
            ->unique('id')
            ->values();

        if ($indicators->isEmpty()) {
            return;
        }

        $agrupaSsIndicatorIds = $establishmentConfigurations
            ->where('agrupa_ss', true)
            ->pluck('met_indicator_id')
            ->unique()
            ->all();

        $ssAplicableIdsByIndicator = $this->dataScopeService->resolveSsAplicableIdsByIndicator(
            $agrupaSsIndicatorIds,
            $center->id,
            $yearId,
            $dependencyId,
            null,
            (int) $indicators->first()->indicator_type_id,
        );

        foreach ($indicators as $indicator) {
            $extraIds = $ssAplicableIdsByIndicator[$indicator->id] ?? [];
            $effectiveAdosadoIds = array_values(array_unique(array_merge($adosadoIds, $extraIds)));

            $periods = $this->aggregatorService->resolvePeriods($indicator, $dependencyId, $yearId);

            if ($periods !== []) {
                MetResult::query()
                    ->where('met_indicator_id', $indicator->id)
                    ->where('cen_center_id', $center->id)
                    ->where('year_id', $yearId)
                    ->where('cen_hie_dependency_id', $dependencyId)
                    ->whereNull('met_measurement_unit_id')
                    ->where('etapa', MetCalculationService::ETAPA_AUTOEVALUACION)
                    ->whereNotIn('periodo', $periods)
                    ->delete();
            }

            $typeId = (int) $indicator->indicator_type_id;

            foreach ($periods as $periodo) {
                $this->calculationService->calculateIndicatorPeriod(
                    $indicator,
                    $center->id,
                    $yearId,
                    $dependencyId,
                    $periodo,
                    $effectiveAdosadoIds,
                    null,
                );

                $periodsToAggregateByType[$typeId][$periodo] = true;
            }
        }
    }

    /**
     * @param  Collection<int, MetIndicatorConfiguration>  $internalConfigurations
     * @param  array<int, array<string, bool>>  $periodsToAggregateByType
     */
    public function runInternalUnitIndicatorCalculations(
        Collection $internalConfigurations,
        CenCenter $center,
        array $adosadoIds,
        int $yearId,
        int $dependencyId,
        array &$periodsToAggregateByType,
    ): void {
        foreach ($internalConfigurations->groupBy('met_measurement_unit_id') as $unitId => $unitConfigs) {
            $unitId = (int) $unitId;

            $indicators = $unitConfigs
                ->pluck('metIndicator')
                ->filter()
                ->unique('id')
                ->values();

            $agrupaSsIndicatorIds = $unitConfigs
                ->where('agrupa_ss', true)
                ->pluck('met_indicator_id')
                ->unique()
                ->all();

            $ssAplicableIdsByIndicator = $this->dataScopeService->resolveSsAplicableIdsByIndicator(
                $agrupaSsIndicatorIds,
                $center->id,
                $yearId,
                $dependencyId,
                $unitId,
                (int) $indicators->first()->indicator_type_id,
            );

            foreach ($indicators as $indicator) {
                $extraIds = $ssAplicableIdsByIndicator[$indicator->id] ?? [];
                $effectiveAdosadoIds = array_values(array_unique(array_merge($adosadoIds, $extraIds)));

                $periods = $this->aggregatorService->resolvePeriods($indicator, $dependencyId, $yearId);

                if ($periods !== []) {
                    MetResult::query()
                        ->where('met_indicator_id', $indicator->id)
                        ->where('cen_center_id', $center->id)
                        ->where('year_id', $yearId)
                        ->where('cen_hie_dependency_id', $dependencyId)
                        ->where('met_measurement_unit_id', $unitId)
                        ->whereNotIn('periodo', $periods)
                        ->delete();
                }

                $typeId = (int) $indicator->indicator_type_id;

                foreach ($periods as $periodo) {
                    $this->calculationService->calculateIndicatorPeriod(
                        $indicator,
                        $center->id,
                        $yearId,
                        $dependencyId,
                        $periodo,
                        $effectiveAdosadoIds,
                        $unitId,
                    );

                    $periodsToAggregateByType[$typeId][$periodo] = true;
                }
            }
        }
    }
}
