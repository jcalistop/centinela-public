<?php

namespace App\Jobs;

use App\Enums\IndicatorType\EtapaVigente;
use App\Models\MetCenterAdosado;
use App\Models\MetCenterInclusion;
use App\Models\MetEstablishmentResult;
use App\Models\MetIndicatorConfiguration;
use App\Services\Metas\MetAutoevaluacionCalculationRunner;
use App\Services\Metas\MetStageCalculationService;
use App\Services\MetCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class MetCalculateResultsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public string $etapa = MetCalculationService::ETAPA_AUTOEVALUACION;

    /** @var array{center_id?: int, indicator_id?: int, periodo?: string, stage?: string, indicator_type_id?: int}|null */
    public ?array $lastFailureContext = null;

    public int $processedCount = 0;

    public int $failedCount = 0;

    public function __construct(
        public int $yearId,
        public int $dependencyId,
        public ?int $indicatorTypeId,
        string $etapa,
    ) {
        $this->etapa = $this->normalizeEtapa($etapa);
    }

    public static function dispatchAutoevaluacion(int $yearId, int $dependencyId, ?int $indicatorTypeId = null): mixed
    {
        return static::dispatch($yearId, $dependencyId, $indicatorTypeId, EtapaVigente::Autoevaluacion->value);
    }

    public static function dispatchStage(string $etapa, int $yearId, int $dependencyId, int $indicatorTypeId): mixed
    {
        return static::dispatch($yearId, $dependencyId, $indicatorTypeId, $etapa);
    }

    public function handle(
        MetCalculationService $calculationService,
        MetStageCalculationService $stageCalculationService,
        MetAutoevaluacionCalculationRunner $autoevaluacionRunner,
    ): void {
        if (in_array($this->etapa, [EtapaVigente::Preliminar->value, EtapaVigente::Final->value], true)) {
            $this->handleStage($stageCalculationService);

            return;
        }

        if ($this->etapa !== EtapaVigente::Autoevaluacion->value) {
            throw new InvalidArgumentException("Etapa inválida para cálculo de metas: {$this->etapa}");
        }

        $indicatorTypeIds = $this->indicatorTypeId !== null
            ? collect([(int) $this->indicatorTypeId])
            : MetCenterInclusion::query()
                ->where('year_id', $this->yearId)
                ->where('cen_hie_dependency_id', $this->dependencyId)
                ->whereNotNull('indicator_type_id')
                ->distinct()
                ->orderBy('indicator_type_id')
                ->pluck('indicator_type_id')
                ->map(fn ($x) => (int) $x);

        if ($indicatorTypeIds->isEmpty()) {
            logger()->info('Cálculo metas: no hay instrumentos con centros incluidos para este SS/año', [
                'year_id' => $this->yearId,
                'cen_hie_dependency_id' => $this->dependencyId,
            ]);

            return;
        }

        foreach ($indicatorTypeIds as $indicatorTypeId) {
            $inclusions = MetCenterInclusion::query()
                ->where('year_id', $this->yearId)
                ->where('cen_hie_dependency_id', $this->dependencyId)
                ->where('indicator_type_id', $indicatorTypeId)
                ->with('center')
                ->get();

            $centers = $inclusions->pluck('center')->filter()->values();

            if ($centers->isEmpty()) {
                continue;
            }

            $includedCenterIds = $centers->pluck('id')->toArray();

            /** @var Collection<int, Collection<int, MetIndicatorConfiguration>> */
            $configurationsGrouped = MetIndicatorConfiguration::query()
                ->where('year_id', $this->yearId)
                ->whereIn('cen_center_id', $includedCenterIds)
                ->where('aplica', true)
                ->whereHas('metIndicator', fn ($q) => $q->where('indicator_type_id', $indicatorTypeId))
                ->with([
                    'metIndicator' => fn ($query) => $query->select('met_indicators.*'),
                    'metIndicator.indicatorType',
                    'metIndicator.formula',
                    'metIndicator.dataFields',
                ])
                ->get()
                ->groupBy('cen_center_id');

            foreach ($centers as $center) {
                $configurations = $configurationsGrouped->get($center->id, collect());

                if ($configurations->isEmpty()) {
                    continue;
                }

                $adosadoIds = [];

                $firstConfig = $configurations->first();
                $instrumentUsesEstablishment = $firstConfig?->metIndicator?->indicatorType
                    && ! $firstConfig->metIndicator->indicatorType->usesInternalUnitMeasurement();

                if ($instrumentUsesEstablishment) {
                    $adosadoIds = MetCenterAdosado::query()
                        ->forInstrument($indicatorTypeId)
                        ->where('cen_center_madre_id', $center->id)
                        ->where('year_id', $this->yearId)
                        ->where('cen_hie_dependency_id', $this->dependencyId)
                        ->pluck('cen_center_adosado_id')
                        ->toArray();
                }

                $establishmentConfigurations = $configurations->filter(function ($c) {
                    return $c->metIndicator
                        && $c->metIndicator->indicatorType
                        && ! $c->metIndicator->indicatorType->usesInternalUnitMeasurement();
                });

                $internalConfigurations = $configurations->filter(function ($c) {
                    return $c->metIndicator
                        && $c->metIndicator->indicatorType
                        && $c->metIndicator->indicatorType->usesInternalUnitMeasurement()
                        && $c->met_measurement_unit_id !== null;
                });

                /** @var array<int, array<string, bool>> */
                $periodsToAggregateByType = [];

                try {
                    $autoevaluacionRunner->runEstablishmentIndicatorCalculations(
                        $establishmentConfigurations,
                        $center,
                        $adosadoIds,
                        $this->yearId,
                        $this->dependencyId,
                        $periodsToAggregateByType,
                    );
                } catch (Throwable $e) {
                    $this->failedCount++;
                    $this->lastFailureContext = [
                        'stage' => $this->etapa,
                        'indicator_type_id' => (int) $indicatorTypeId,
                        'center_id' => (int) $center->id,
                    ];
                    Log::warning('[metas.calculate] unit failed (establishment)', [
                        'year_id' => $this->yearId,
                        'cen_hie_dependency_id' => $this->dependencyId,
                        'indicator_type_id' => (int) $indicatorTypeId,
                        'center_id' => (int) $center->id,
                        'etapa' => $this->etapa,
                        'exception' => $e->getMessage(),
                    ]);
                }

                try {
                    $autoevaluacionRunner->runInternalUnitIndicatorCalculations(
                        $internalConfigurations,
                        $center,
                        $adosadoIds,
                        $this->yearId,
                        $this->dependencyId,
                        $periodsToAggregateByType,
                    );
                } catch (Throwable $e) {
                    $this->failedCount++;
                    $this->lastFailureContext = [
                        'stage' => $this->etapa,
                        'indicator_type_id' => (int) $indicatorTypeId,
                        'center_id' => (int) $center->id,
                    ];
                    Log::warning('[metas.calculate] unit failed (internal)', [
                        'year_id' => $this->yearId,
                        'cen_hie_dependency_id' => $this->dependencyId,
                        'indicator_type_id' => (int) $indicatorTypeId,
                        'center_id' => (int) $center->id,
                        'etapa' => $this->etapa,
                        'exception' => $e->getMessage(),
                    ]);
                }

                foreach ($periodsToAggregateByType as $typeId => $periodMap) {
                    foreach (array_keys($periodMap) as $periodo) {
                        try {
                            $calculationService->calculateEstablishmentResult(
                                $center->id,
                                $this->yearId,
                                $this->dependencyId,
                                $periodo,
                                (int) $typeId,
                            );
                            $this->processedCount++;
                        } catch (Throwable $e) {
                            $this->failedCount++;
                            $this->lastFailureContext = [
                                'stage' => $this->etapa,
                                'indicator_type_id' => (int) $typeId,
                                'center_id' => (int) $center->id,
                                'periodo' => $periodo,
                            ];
                            Log::warning('[metas.calculate] unit failed (establishment_result)', [
                                'year_id' => $this->yearId,
                                'cen_hie_dependency_id' => $this->dependencyId,
                                'indicator_type_id' => (int) $typeId,
                                'center_id' => (int) $center->id,
                                'periodo' => $periodo,
                                'etapa' => $this->etapa,
                                'exception' => $e->getMessage(),
                            ]);
                        }
                    }

                    if ($periodMap !== []) {
                        try {
                            $calculationService->calculateEstablishmentResult(
                                $center->id,
                                $this->yearId,
                                $this->dependencyId,
                                'ANUAL',
                                (int) $typeId,
                            );
                            $this->processedCount++;
                        } catch (Throwable $e) {
                            $this->failedCount++;
                            $this->lastFailureContext = [
                                'stage' => $this->etapa,
                                'indicator_type_id' => (int) $typeId,
                                'center_id' => (int) $center->id,
                                'periodo' => 'ANUAL',
                            ];
                            Log::warning('[metas.calculate] unit failed (establishment_result)', [
                                'year_id' => $this->yearId,
                                'cen_hie_dependency_id' => $this->dependencyId,
                                'indicator_type_id' => (int) $typeId,
                                'center_id' => (int) $center->id,
                                'periodo' => 'ANUAL',
                                'etapa' => $this->etapa,
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
                            ->where('cen_center_id', $center->id)
                            ->where('year_id', $this->yearId)
                            ->where('cen_hie_dependency_id', $this->dependencyId)
                            ->where('indicator_type_id', (int) $typeId)
                            ->where('etapa', MetCalculationService::ETAPA_AUTOEVALUACION)
                            ->whereNotIn('periodo', $validEstablishmentPeriods)
                            ->delete();
                    }
                }
            }
        }

        if ($this->failedCount > 0) {
            Log::warning('[metas.calculate] completed with failures', [
                'uuid' => $this->job?->uuid(),
                'year_id' => $this->yearId,
                'cen_hie_dependency_id' => $this->dependencyId,
                'indicator_type_id' => $this->indicatorTypeId,
                'etapa' => $this->etapa,
                'processed' => $this->processedCount,
                'failed_units' => $this->failedCount,
                'last_context' => $this->lastFailureContext,
            ]);
        }
    }

    private function handleStage(MetStageCalculationService $stageCalculationService): void
    {
        $indicatorTypeIds = $this->indicatorTypeId !== null
            ? collect([(int) $this->indicatorTypeId])
            : MetCenterInclusion::query()
                ->where('year_id', $this->yearId)
                ->where('cen_hie_dependency_id', $this->dependencyId)
                ->whereNotNull('indicator_type_id')
                ->distinct()
                ->pluck('indicator_type_id')
                ->map(fn ($x) => (int) $x);

        foreach ($indicatorTypeIds as $indicatorTypeId) {
            try {
                $stageCalculationService->calculateForStage(
                    $this->etapa,
                    $indicatorTypeId,
                    $this->yearId,
                    $this->dependencyId,
                );
                $this->processedCount++;
            } catch (Throwable $e) {
                $this->failedCount++;
                $this->lastFailureContext = [
                    'stage' => $this->etapa,
                    'indicator_type_id' => (int) $indicatorTypeId,
                ];
                Log::warning('[metas.calculate] unit failed (stage)', [
                    'uuid' => $this->job?->uuid(),
                    'year_id' => $this->yearId,
                    'cen_hie_dependency_id' => $this->dependencyId,
                    'indicator_type_id' => (int) $indicatorTypeId,
                    'etapa' => $this->etapa,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    private function normalizeEtapa(string $etapa): string
    {
        $allowed = [
            EtapaVigente::Autoevaluacion->value,
            EtapaVigente::Preliminar->value,
            EtapaVigente::Final->value,
        ];

        if (! in_array($etapa, $allowed, true)) {
            throw new InvalidArgumentException("Etapa inválida para cálculo de metas: {$etapa}");
        }

        return $etapa;
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[metas.calculate] job failed', [
            'uuid' => $this->job?->uuid(),
            'year_id' => $this->yearId,
            'cen_hie_dependency_id' => $this->dependencyId,
            'indicator_type_id' => $this->indicatorTypeId,
            'etapa' => $this->etapa,
            'processed' => $this->processedCount,
            'failed_units' => $this->failedCount,
            'last_context' => $this->lastFailureContext,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
