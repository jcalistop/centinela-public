<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Livewire\MetasInstrument\Concerns\InteractsWithComplexFormulaVariablesModal;
use App\Livewire\MetasInstrument\Concerns\InteractsWithMetStageCapture;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\CenCenter;
use App\Models\IndicatorType;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMeasurementUnit;
use App\Models\MetResult;
use App\Services\Metas\MetEstablishmentIndicatorConfigService;
use App\Services\Metas\MetEstablishmentResponsibleAccess;
use App\Services\Metas\MetFieldSsConfigService;
use App\Services\Metas\MetIndicatorCalculationDataScopeService;
use App\Services\Metas\MetIndicatorCalculationErrorService;
use App\Services\Metas\MetIndicatorDataModalService;
use App\Services\Metas\MetIndicatorLastLoadedMonthService;
use App\Services\Metas\MetResultAggregationPresenter;
use App\Services\MetCalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class EstablishmentResultsDetailComponent extends Component
{
    use InteractsWithComplexFormulaVariablesModal;
    use InteractsWithMetStageCapture;
    use ResolvesInstrumentIndicatorTypeId;

    public int $indicatorTypeId;

    public int $centerId;

    public ?int $measurementUnitId = null;

    public bool $soloComprometidos = false;

    /** @var array<string, int> */
    private const PERIODO_ORDER = [
        'MES-01' => 1,
        'MES-02' => 2,
        'MES-03' => 3,
        'MES-04' => 4,
        'MES-05' => 5,
        'MES-06' => 6,
        'MES-07' => 7,
        'MES-08' => 8,
        'MES-09' => 9,
        'MES-10' => 10,
        'MES-11' => 11,
        'MES-12' => 12,
        'TRIM-1' => 13,
        'TRIM-2' => 14,
        'TRIM-3' => 15,
        'TRIM-4' => 16,
        'SEM-1' => 17,
        'SEM-2' => 18,
        'ANUAL' => 19,
    ];

    public function mount(?int $indicatorTypeId = null, ?int $centerId = null): void
    {
        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId($indicatorTypeId);
        $this->centerId = (int) $centerId;

        $unidad = request()->integer('unidad');
        if ($unidad > 0) {
            $this->measurementUnitId = $this->resolveMeasurementUnitId($unidad);
        }
    }

    public function openDataModal(int $indicatorId): void
    {
        $payload = app(MetIndicatorDataModalService::class)->payload(
            $this->indicatorTypeId,
            $indicatorId,
            $this->centerId,
            $this->authUser(),
            $this->currentIndicatorYear()->id,
            restrictToAutoevaluacionEtapa: true,
        );

        $this->dispatch('open-data-modal', ...$payload);
    }

    public function render(): View
    {
        $indicatorType = IndicatorType::query()->findOrFail($this->indicatorTypeId);
        $center = CenCenter::query()->findOrFail($this->centerId);
        $usesUnitsSummary = $indicatorType->usesInternalUnitMeasurement();
        $showAveragedUnitsBanner = $usesUnitsSummary && $this->measurementUnitId === null;

        $measurementUnit = $this->measurementUnitId !== null
            ? MetMeasurementUnit::query()->find($this->measurementUnitId)
            : null;

        $rawIndicatorResults = MetResult::query()
            ->currentYear()
            ->etapaAutoevaluacion()
            ->where('cen_center_id', $this->centerId)
            ->when($this->measurementUnitId !== null, function ($query): void {
                $query->where('met_measurement_unit_id', $this->measurementUnitId);
            })
            ->whereHas('indicator', function ($q): void {
                $q->where('indicator_type_id', $this->indicatorTypeId);
            })
            ->with('indicator.dataFields')
            ->get();

        $mergedResults = $showAveragedUnitsBanner
            ? $this->mergeUnitLevelResultsIfNeeded($rawIndicatorResults)
            : $rawIndicatorResults;

        $indicatorResults = $mergedResults
            ->groupBy(fn ($r) => $r->cen_center_id.'-'.$r->met_indicator_id)
            ->map(function ($group) {
                $anual = $group->firstWhere('periodo', 'ANUAL');
                if ($anual) {
                    $lastNonAnual = $group
                        ->filter(fn ($r) => $r->periodo !== 'ANUAL' && $r->resultado !== null)
                        ->sortByDesc(fn ($r) => self::PERIODO_ORDER[$r->periodo] ?? 0)
                        ->first();
                    $anual->ultimo_periodo = $lastNonAnual?->periodo;

                    return $anual;
                }

                $indicator = $group->first()->indicator;
                $decimales = $indicator?->decimales ?? 2;
                $decimalesModo = $indicator?->decimales_modo?->value ?? 'redondear';

                $effectiveFields = $indicator
                    ? app(MetFieldSsConfigService::class)->applyEffectiveTypes(
                        $indicator->dataFields,
                        (int) $this->authUser()->cen_hie_dependency_id,
                        $this->currentIndicatorYear()->id,
                    )
                    : collect();

                $aggregated = app(MetResultAggregationPresenter::class)->aggregatePeriodResultsToAnnualSummary(
                    $group,
                    $effectiveFields,
                    $decimales,
                    $decimalesModo,
                    self::PERIODO_ORDER,
                    $indicator,
                );

                $synthetic = $group->first()->replicate();
                $synthetic->periodo = 'ANUAL';
                $synthetic->numerador = $aggregated['numerador'];
                $synthetic->denominador = $aggregated['denominador'];
                $synthetic->resultado = $aggregated['resultado'];
                $synthetic->error_message = $aggregated['error_message'];
                $synthetic->ultimo_periodo = $aggregated['ultimo_periodo'];
                $synthetic->setRelation('indicator', $group->first()->indicator);

                return $synthetic;
            })
            ->values()
            ->sortBy(fn ($r) => $r->indicator?->orden ?? 999);

        $configurationRows = MetIndicatorConfiguration::query()
            ->currentYear()
            ->where('cen_center_id', $this->centerId)
            ->when(! $usesUnitsSummary, fn ($q) => $q->whereNull('met_measurement_unit_id'))
            ->when($this->measurementUnitId !== null, fn ($q) => $q->where('met_measurement_unit_id', $this->measurementUnitId))
            ->get();

        $indicatorApplies = $configurationRows
            ->groupBy('met_indicator_id')
            ->map(fn (Collection $rows): bool => $rows->contains(
                fn (MetIndicatorConfiguration $config): bool => (bool) ($config->aplica ?? true),
            ));

        $configurations = $configurationRows->keyBy('met_indicator_id');

        $byIndicator = $indicatorResults
            ->groupBy('met_indicator_id');

        // SDD-013: aplica=false no se lista (pueden quedar MetResult residuales tras desmarcar Aplica).
        $byIndicator = $byIndicator->filter(function ($results, $indicatorId) use ($indicatorApplies): bool {
            if (! $indicatorApplies->has($indicatorId)) {
                return true;
            }

            return (bool) $indicatorApplies->get($indicatorId);
        });

        if ($this->soloComprometidos) {
            $byIndicator = $byIndicator->filter(function ($results, $indicatorId) use ($configurations): bool {
                $config = $configurations->get($indicatorId);

                return (bool) ($config?->seleccionado ?? false);
            });
        }

        $calculationService = app(MetCalculationService::class);
        $globalCompliance = $this->measurementUnitId !== null
            ? $calculationService->sumWeightedComplianceForUnit(
                $this->centerId,
                $this->currentIndicatorYear()->id,
                (int) $this->authUser()->cen_hie_dependency_id,
                'ANUAL',
                $this->indicatorTypeId,
                $this->measurementUnitId,
            )
            : $calculationService->sumWeightedComplianceForEstablishment(
                $this->centerId,
                $this->currentIndicatorYear()->id,
                (int) $this->authUser()->cen_hie_dependency_id,
                'ANUAL',
                $this->indicatorTypeId,
            );

        $weightedComplianceByIndicator = [];
        foreach ($byIndicator as $indicatorId => $periodResults) {
            $config = $configurations->get($indicatorId);
            $annualResult = $periodResults->firstWhere('periodo', 'ANUAL') ?? $periodResults->first();

            if (
                $config === null
                || ! $config->seleccionado
                || $annualResult === null
                || $annualResult->error_message
                || $annualResult->resultado === null
            ) {
                continue;
            }

            $weightedComplianceByIndicator[$indicatorId] = $calculationService->weightedComplianceFromResult(
                $config,
                (float) $annualResult->resultado,
            );
        }

        $excludeMeasurementUnitRows = ! $indicatorType->usesInternalUnitMeasurement();
        $indicatorsForLoad = $byIndicator
            ->map(fn ($periodResults) => $periodResults->first()?->indicator)
            ->filter()
            ->values();

        $calculationErrorService = app(MetIndicatorCalculationErrorService::class);

        // SDD-190: con varias unidades promediadas no hay una configuración única por indicador.
        $configsForLoad = $showAveragedUnitsBanner ? null : $configurations;

        if ($configsForLoad !== null) {
            $dataScopeService = app(MetIndicatorCalculationDataScopeService::class);
            $dataScopeService->preloadAdosadosForInstrument(
                $this->indicatorTypeId,
                $this->currentIndicatorYear()->id,
                (int) $this->authUser()->cen_hie_dependency_id,
            );
            $dataScopeService->preloadAplicaCentersForIndicators(
                $indicatorsForLoad->map(fn ($indicator): int => (int) $indicator->id)->all(),
                $this->currentIndicatorYear()->id,
                (int) $this->authUser()->cen_hie_dependency_id,
                $this->measurementUnitId,
            );
        }

        $dataLoadSnapshotsByIndicator = app(MetIndicatorLastLoadedMonthService::class)
            ->snapshotsByCenterAndIndicators(
                $this->centerId,
                $indicatorsForLoad,
                (int) $this->authUser()->cen_hie_dependency_id,
                $this->currentIndicatorYear()->id,
                $excludeMeasurementUnitRows,
                fn (int $indicatorId, array $centerIds): Collection => $calculationErrorService->errorsByCenterId(
                    $indicatorId,
                    (int) $this->authUser()->cen_hie_dependency_id,
                    $this->currentIndicatorYear()->id,
                    $centerIds,
                ),
                $configsForLoad,
            );

        $yearId = $this->currentIndicatorYear()->id;
        $depId = (int) $this->authUser()->cen_hie_dependency_id;
        $configIssue = app(MetEstablishmentIndicatorConfigService::class)
            ->issuesByCenterId(
                $this->indicatorTypeId,
                [$this->centerId],
                $yearId,
                $depId,
                $usesUnitsSummary,
            )
            ->get($this->centerId);

        $user = $this->authUser();
        $canEditEstablishmentIndicators = app(MetEstablishmentResponsibleAccess::class)
            ->canShowConvenioCtaOnEstablishmentResults(
                $user,
                $this->centerId,
                $yearId,
                $this->indicatorTypeId,
            );

        return view('livewire.metas-instrument.establishment-results-detail', [
            'center' => $center,
            'measurementUnit' => $measurementUnit,
            'showAveragedUnitsBanner' => $showAveragedUnitsBanner,
            'byIndicator' => $byIndicator,
            'configurations' => $configurations,
            'dataLoadSnapshotsByIndicator' => $dataLoadSnapshotsByIndicator,
            'footerCumplimientoGlobal' => $globalCompliance['resultado_global'],
            'weightedComplianceByIndicator' => $weightedComplianceByIndicator,
            'calculationService' => $calculationService,
            'canWrite' => $this->canWriteStage(EtapaVigente::Autoevaluacion),
            'indicatorTypeRoute' => $indicatorType->route,
            'indicatorType' => $indicatorType,
            'indicatorYearAno' => $this->currentIndicatorYear()->ano,
            'configIssue' => $configIssue,
            'canEditEstablishmentIndicators' => $canEditEstablishmentIndicators,
        ]);
    }

    /** @param Collection<int, MetResult> $results */
    private function mergeUnitLevelResultsIfNeeded(Collection $results): Collection
    {
        $type = IndicatorType::query()->find($this->indicatorTypeId);

        if (! $type?->usesInternalUnitMeasurement()) {
            return $results;
        }

        return $results
            ->groupBy(fn (MetResult $r) => $r->cen_center_id.'-'.$r->met_indicator_id.'-'.$r->periodo)
            ->map(function (Collection $rows): MetResult {
                $first = $rows->firstOrFail();
                $valid = $rows->filter(fn (MetResult $r) => $r->error_message === null && $r->resultado !== null);

                if ($valid->isEmpty()) {
                    return $rows->sortByDesc('id')->first();
                }

                $synthetic = $first->replicate();
                $synthetic->numerador = (float) $rows->avg('numerador');
                $synthetic->denominador = (float) $rows->avg('denominador');
                $synthetic->resultado = (float) $valid->avg('resultado');
                $synthetic->error_message = null;
                $synthetic->met_measurement_unit_id = null;
                $synthetic->cumple_meta = null;

                return $synthetic;
            })
            ->values();
    }

    private function resolveMeasurementUnitId(int $unitId): int
    {
        $unit = MetMeasurementUnit::query()
            ->where('id', $unitId)
            ->where('cen_center_id', $this->centerId)
            ->where('year_id', $this->currentIndicatorYear()->id)
            ->where('cen_hie_dependency_id', (int) $this->authUser()->cen_hie_dependency_id)
            ->active()
            ->first();

        abort_unless($unit !== null, 404);

        return $unit->id;
    }

    protected function complexFormulaVariablesModalIndicatorTypeId(): int
    {
        return $this->indicatorTypeId;
    }
}
