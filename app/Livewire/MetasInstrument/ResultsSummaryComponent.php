<?php

namespace App\Livewire\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Livewire\MetasInstrument\Concerns\ResolvesInstrumentIndicatorTypeId;
use App\Models\CenCenter;
use App\Models\IndicatorType;
use App\Models\MetCenterInclusion;
use App\Models\MetEstablishmentResult;
use App\Models\MetMeasurementUnit;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetEstablishmentAgreementService;
use App\Services\Metas\MetEstablishmentIndicatorConfigService;
use App\Services\MetCalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ResultsSummaryComponent extends Component
{
    use ResolvesInstrumentIndicatorTypeId;

    public int $indicatorTypeId;

    public function mount(?int $indicatorTypeId = null): void
    {
        $this->indicatorTypeId = $this->resolveInstrumentIndicatorTypeId($indicatorTypeId);
    }

    public function render(): View
    {
        $yearId = $this->currentIndicatorYear()->id;
        $depId = (int) $this->authUser()->cen_hie_dependency_id;

        $includedCenterIds = MetCenterInclusion::query()
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->pluck('cen_center_id');

        $resultCenterIds = MetEstablishmentResult::query()
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $depId)
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('periodo', 'ANUAL')
            ->distinct()
            ->pluck('cen_center_id');

        $centerIds = $includedCenterIds->merge($resultCenterIds)->unique()->values();

        $centers = $centerIds->isEmpty()
            ? collect()
            : CenCenter::query()
                ->whereIn('id', $centerIds)
                ->orderBy('nombre_corto')
                ->get();

        $allAnnualByEtapa = MetEstablishmentResult::query()
            ->currentYear()
            ->where('indicator_type_id', $this->indicatorTypeId)
            ->where('periodo', 'ANUAL')
            ->where(function ($query): void {
                $query->etapaAutoevaluacion()
                    ->orWhere('etapa', EtapaVigente::Preliminar->value)
                    ->orWhere('etapa', EtapaVigente::Final->value);
            })
            ->get();

        $autoByCenter = $this->annualGlobalByCenter(
            $allAnnualByEtapa->filter(function (MetEstablishmentResult $result): bool {
                return $result->etapa === EtapaVigente::Autoevaluacion->value
                    || $result->etapa === null
                    || $result->etapa === '';
            }),
        );

        $prelimByCenter = $this->annualGlobalByCenter(
            $allAnnualByEtapa->where('etapa', EtapaVigente::Preliminar->value),
        );

        $finalByCenter = $this->annualGlobalByCenter(
            $allAnnualByEtapa->where('etapa', EtapaVigente::Final->value),
        );

        /** @var array<int, array<string, float|null>> $globalResultsByEtapa */
        $globalResultsByEtapa = [];
        /** @var array<int, float|null> $latestGlobalByCenter */
        $latestGlobalByCenter = [];
        foreach ($centers as $center) {
            $globalResultsByEtapa[$center->id] = [
                EtapaVigente::Autoevaluacion->value => $autoByCenter[$center->id] ?? null,
                EtapaVigente::Preliminar->value => $prelimByCenter[$center->id] ?? null,
                EtapaVigente::Final->value => $finalByCenter[$center->id] ?? null,
            ];
            $latestGlobalByCenter[$center->id] = $this->resolveLatestGlobalResult($globalResultsByEtapa[$center->id]);
        }

        $centers = $this->sortCentersByLatestGlobal($centers, $latestGlobalByCenter);

        $indicatorType = IndicatorType::query()->findOrFail($this->indicatorTypeId);

        $stageLabels = [
            EtapaVigente::Autoevaluacion->value => 'Autoevaluación',
            EtapaVigente::Preliminar->value => 'Ev. preliminar',
            EtapaVigente::Final->value => 'Ev. final',
        ];

        $usesInternalUnitMeasurement = $indicatorType->usesInternalUnitMeasurement();

        $includedIdsForIssues = $includedCenterIds->map(fn ($id): int => (int) $id)->values()->all();
        sort($includedIdsForIssues);

        $configIssuesByCenterId = app(MetEstablishmentIndicatorConfigService::class)->issuesByCenterId(
            $this->indicatorTypeId,
            $includedIdsForIssues,
            $yearId,
            $depId,
            $usesInternalUnitMeasurement,
        );

        $agreementsByCenterId = app(MetEstablishmentAgreementService::class)->agreementsByCenterId(
            $this->indicatorTypeId,
            $includedIdsForIssues,
            $yearId,
        );

        /** @var Collection<int, Collection<int, MetMeasurementUnit>> $unitsByCenter */
        $unitsByCenter = collect();

        /** @var array<int, array<int, array<string, float|null>>> $globalResultsByUnitAndEtapa */
        $globalResultsByUnitAndEtapa = [];

        if ($usesInternalUnitMeasurement && $centers->isNotEmpty()) {
            $units = MetMeasurementUnit::query()
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $depId)
                ->whereIn('cen_center_id', $centers->pluck('id'))
                ->active()
                ->orderBy('codigo')
                ->get();

            $unitsByCenter = $units->groupBy('cen_center_id');

            $etapas = [
                EtapaVigente::Autoevaluacion->value,
                EtapaVigente::Preliminar->value,
                EtapaVigente::Final->value,
            ];

            $globalResultsByUnitAndEtapa = app(MetCalculationService::class)
                ->weightedGlobalResultsByCenterUnitAndEtapa(
                    $units,
                    $yearId,
                    $depId,
                    $this->indicatorTypeId,
                    'ANUAL',
                    $etapas,
                );
        }

        $canConfigureEstablishments = $this->authUser()->hasRole(['control-gestion', 'admin']);
        $configureEstablishmentsUrl = $canConfigureEstablishments
            ? route('metas.instrument.establishments.configure', $indicatorType)
            : null;

        return view('livewire.metas-instrument.results-summary', compact(
            'centers',
            'globalResultsByEtapa',
            'latestGlobalByCenter',
            'indicatorType',
            'stageLabels',
            'usesInternalUnitMeasurement',
            'unitsByCenter',
            'globalResultsByUnitAndEtapa',
            'configIssuesByCenterId',
            'agreementsByCenterId',
            'canConfigureEstablishments',
            'configureEstablishmentsUrl',
        ));
    }

    /**
     * Ordena establecimientos de mejor a peor cumplimiento global (sin resultado al final).
     *
     * @param  Collection<int, CenCenter>  $centers
     * @param  array<int, float|null>  $latestGlobalByCenter
     * @return Collection<int, CenCenter>
     */
    private function sortCentersByLatestGlobal(Collection $centers, array $latestGlobalByCenter): Collection
    {
        return $centers
            ->sort(function (CenCenter $a, CenCenter $b) use ($latestGlobalByCenter): int {
                $aVal = $latestGlobalByCenter[$a->id] ?? null;
                $bVal = $latestGlobalByCenter[$b->id] ?? null;

                if ($aVal === null && $bVal === null) {
                    return strcmp((string) $a->nombre_corto, (string) $b->nombre_corto);
                }

                if ($aVal === null) {
                    return 1;
                }

                if ($bVal === null) {
                    return -1;
                }

                if ($aVal === $bVal) {
                    return strcmp((string) $a->nombre_corto, (string) $b->nombre_corto);
                }

                return $bVal <=> $aVal;
            })
            ->values();
    }

    /**
     * Último resultado global disponible: final → preliminar → autoevaluación.
     *
     * @param  array<string, float|null>  $globalsByEtapa
     */
    private function resolveLatestGlobalResult(array $globalsByEtapa): ?float
    {
        foreach (
            [
                EtapaVigente::Final->value,
                EtapaVigente::Preliminar->value,
                EtapaVigente::Autoevaluacion->value,
            ] as $etapa
        ) {
            if (isset($globalsByEtapa[$etapa]) && $globalsByEtapa[$etapa] !== null) {
                return $globalsByEtapa[$etapa];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, MetEstablishmentResult>  $results
     * @return array<int, float>
     */
    private function annualGlobalByCenter(Collection $results): array
    {
        $byCenter = [];
        foreach ($results as $result) {
            if ($result->resultado_global !== null) {
                $byCenter[$result->cen_center_id] = (float) $result->resultado_global;
            }
        }

        return $byCenter;
    }

    private function authUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function currentIndicatorYear(): Year
    {
        $year = session()->get('indicator_year');
        abort_unless($year instanceof Year, 500);

        return $year;
    }
}
