<?php

namespace App\Services\Metas;

use App\Enums\Metas\DataFieldComponente;
use App\Enums\Periodicidad;
use App\Models\CenCenter;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetResult;
use App\Models\User;
use App\Services\MetCalculationService;
use App\Support\Metas\MetIndicatorMiniFichaPresenter;
use App\Support\Metas\MetIndicatorPrimaryComponentPresenter;
use Illuminate\Support\Collection;

class MetIndicatorDataModalService
{
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

    public function downloadUrl(
        User $user,
        IndicatorType $indicatorType,
        MetIndicator $indicator,
        CenCenter $center,
        int $yearId,
    ): ?string {
        return app(MetInstrumentDataEntryAccess::class)->complexFormulaDataDetailDownloadUrl(
            $user,
            $indicatorType,
            $indicator,
            $center,
            $yearId,
            forEstablishmentResultsView: true,
        );
    }

    /**
     * @return array{
     *     title: string,
     *     clave: string,
     *     centerNombre: string,
     *     centerCodigo: string,
     *     metaNacional: float,
     *     metaEfectiva: float,
     *     metaOperador: string,
     *     umbralInferior: float,
     *     umbralSuperior: float,
     *     periodos: list<array<string, mixed>>,
     *     isSumatoria: bool,
     *     usesComplexPrimaryComponents: bool,
     *     dataEntryUrl: string|null,
     *     decimales: int,
     *     unidadMedida: string|null,
     *     ficha: array<string, mixed>|null
     * }
     */
    public function payload(
        int $indicatorTypeId,
        int $indicatorId,
        int $centerId,
        User $user,
        int $yearId,
        bool $restrictToAutoevaluacionEtapa = true,
    ): array {
        $indicator = MetIndicator::query()
            ->where('id', $indicatorId)
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('year_id', $yearId)
            ->with(['dataFields', 'formula'])
            ->first();

        $indicatorType = IndicatorType::query()->findOrFail($indicatorTypeId);
        $center = CenCenter::query()->findOrFail($centerId);
        $usesUnits = $indicatorType->usesInternalUnitMeasurement();

        $configQuery = MetIndicatorConfiguration::query()
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $user->cen_hie_dependency_id)
            ->where('cen_center_id', $centerId)
            ->where('met_indicator_id', $indicatorId);

        if ($usesUnits) {
            $metaEfectivaPromedio = $configQuery->clone()->whereNotNull('met_measurement_unit_id')->avg('meta_efectiva');
            $config = null;
        } else {
            $config = $configQuery->clone()->whereNull('met_measurement_unit_id')->first();
            $metaEfectivaPromedio = null;
        }

        $metaNacional = (float) ($indicator?->meta_valor ?? 0);
        $metaEfectiva = $usesUnits
            ? (float) ($metaEfectivaPromedio ?? $metaNacional)
            : ($config?->meta_efectiva !== null ? (float) $config->meta_efectiva : $metaNacional);

        $resultsQuery = MetResult::query()
            ->where('cen_hie_dependency_id', $user->cen_hie_dependency_id)
            ->where('year_id', $yearId)
            ->where('cen_center_id', $centerId)
            ->where('met_indicator_id', $indicatorId)
            ->whereHas('indicator', function ($q) use ($indicatorTypeId): void {
                $q->where('indicator_type_id', $indicatorTypeId);
            });

        if ($restrictToAutoevaluacionEtapa) {
            $resultsQuery->etapaAutoevaluacion();
        }

        $mergedForPeriod = $this->mergeUnitLevelResultsIfNeeded($resultsQuery->get(), $indicatorType)
            ->keyBy('periodo');

        $periodicidadOperativa = $indicator
            ? app(MetIndicatorPeriodicityResolver::class)->resolveForCarga(
                $indicator,
                (int) $user->cen_hie_dependency_id,
                $yearId,
            )
            : Periodicidad::Anual;

        $expectedPeriods = array_keys(MetIndicator::PERIODOS[$periodicidadOperativa->value] ?? []);

        $calculation = app(MetCalculationService::class);

        $periodos = collect($expectedPeriods)
            ->map(function (string $periodo) use ($mergedForPeriod, $metaEfectiva, $calculation, $indicator): array {
                $r = $mergedForPeriod->get($periodo);

                if (! $r) {
                    return [
                        'periodo' => $periodo,
                        'numerador' => null,
                        'denominador' => null,
                        'resultado' => null,
                        'error' => null,
                        'cumple_meta' => null,
                        'cumplimiento' => null,
                    ];
                }

                $cumplimiento = $r->error_message
                    ? null
                    : $calculation->indicatorCompliancePercent($indicator, (float) $r->resultado, $metaEfectiva);

                return [
                    'periodo' => $r->periodo,
                    'numerador' => $r->numerador !== null ? (float) $r->numerador : null,
                    'denominador' => $r->denominador !== null ? (float) $r->denominador : null,
                    'resultado' => $r->error_message ? null : (float) $r->resultado,
                    'error' => $r->error_message,
                    'cumple_meta' => (bool) $r->cumple_meta,
                    'cumplimiento' => $cumplimiento !== null ? round($cumplimiento, 2) : null,
                ];
            })
            ->values()
            ->all();

        $effectiveFields = $indicator
            ? app(MetFieldSsConfigService::class)->applyEffectiveTypes(
                $indicator->dataFields,
                (int) $user->cen_hie_dependency_id,
                $yearId,
            )
            : collect();

        $aggregationPresenter = app(MetResultAggregationPresenter::class);
        $numeradorFields = $effectiveFields->where('componente', DataFieldComponente::Numerador);
        $isSumatoria = $numeradorFields->isNotEmpty()
            && $aggregationPresenter->componentUsesAccumulativeAggregation($numeradorFields);

        $usesComplexPrimaryComponents = $indicator
            ? MetIndicatorPrimaryComponentPresenter::usesComplexPrimaryComponents($indicator->dataFields)
            : false;

        if ($isSumatoria && $indicator) {
            $decimales = $indicator->decimales ?? 2;
            $decimalesModo = $indicator->decimales_modo?->value ?? 'redondear';

            if ($usesComplexPrimaryComponents) {
                $periodos = $aggregationPresenter->appendComplexCumulativePeriodData(
                    $periodos,
                    $effectiveFields,
                    $indicator,
                    $centerId,
                    $yearId,
                    (int) $user->cen_hie_dependency_id,
                    $decimales,
                    $decimalesModo,
                    $config,
                );
            } else {
                $periodos = $aggregationPresenter->appendCumulativePeriodData(
                    $periodos,
                    $effectiveFields,
                    $indicator,
                    $decimales,
                    $decimalesModo,
                );
            }

            $periodos = $this->appendCumulativeCompliance(
                $periodos,
                $calculation,
                $indicator,
                $metaEfectiva,
            );
        }

        $dataEntryUrl = $indicator
            ? $this->downloadUrl($user, $indicatorType, $indicator, $center, $yearId)
            : null;

        $ficha = $indicator
            ? app(MetIndicatorMiniFichaPresenter::class)->forDataModal(
                $indicator,
                (int) $user->cen_hie_dependency_id,
                $yearId,
                $metaNacional,
                $metaEfectiva,
                $config?->seleccionado,
            )
            : null;

        return [
            'title' => $indicator?->nombre ?? "Indicador #{$indicatorId}",
            'clave' => $indicator?->clave ?? '',
            'centerNombre' => (string) ($center->nombre_corto ?: $center->nombre_oficial ?: ''),
            'centerCodigo' => (string) ($center->codigo ?? ''),
            'metaNacional' => $metaNacional,
            'metaEfectiva' => $metaEfectiva,
            'metaOperador' => $indicator?->meta_operador?->value ?? '>=',
            'umbralInferior' => (float) ($indicator?->umbral_inferior ?? 0),
            'umbralSuperior' => (float) ($indicator?->umbral_superior ?? 100),
            'periodos' => $periodos,
            'isSumatoria' => $isSumatoria,
            'usesComplexPrimaryComponents' => $usesComplexPrimaryComponents,
            'dataEntryUrl' => $dataEntryUrl,
            'decimales' => $indicator?->decimales ?? 2,
            'unidadMedida' => $indicator?->unidad_medida,
            'ficha' => $ficha,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $periodos
     * @return list<array<string, mixed>>
     */
    private function appendCumulativeCompliance(
        array $periodos,
        MetCalculationService $calculation,
        MetIndicator $indicator,
        float $metaEfectiva,
    ): array {
        foreach ($periodos as &$periodo) {
            $resultadoAcumulado = $periodo['resultado_acumulado'] ?? null;

            if ($resultadoAcumulado === null) {
                $periodo['cumplimiento_acumulado'] = null;
                $periodo['cumple_meta_acumulado'] = null;

                continue;
            }

            $cumplimiento = $calculation->indicatorCompliancePercent(
                $indicator,
                (float) $resultadoAcumulado,
                $metaEfectiva,
            );

            $periodo['cumplimiento_acumulado'] = $cumplimiento !== null
                ? round($cumplimiento, 2)
                : null;
            $periodo['cumple_meta_acumulado'] = $calculation->indicatorMeetsMeta(
                $indicator,
                (float) $resultadoAcumulado,
                $metaEfectiva,
            );
        }
        unset($periodo);

        return $periodos;
    }

    /** @param Collection<int, MetResult> $results */
    private function mergeUnitLevelResultsIfNeeded(Collection $results, IndicatorType $indicatorType): Collection
    {
        if (! $indicatorType->usesInternalUnitMeasurement()) {
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
}
