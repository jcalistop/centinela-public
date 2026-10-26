<?php

namespace App\Services\Metas;

use App\Models\MetIndicator;
use App\Models\MetResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MetIndicatorCalculationErrorService
{
    public function __construct(
        private readonly MetIndicatorDesempenoByEstablishmentService $desempenoByEstablishmentService,
    ) {}

    /**
     * IDs de indicadores con al menos un error de cálculo (persistido o por agregación anual).
     *
     * @param  array<int, int>  $indicatorIds
     * @param  array<int, array{rows?: list<array<string, mixed>>}>|null  $preloadedDesempenoBatch
     * @return Collection<int, int>
     */
    public function indicatorIdsWithCalculationErrors(
        array $indicatorIds,
        int $cenHieDependencyId,
        int $yearId,
        ?array $preloadedDesempenoBatch = null,
    ): Collection {
        if ($indicatorIds === []) {
            return collect();
        }

        $persisted = $this->indicatorIdsWithPersistedCalculationErrors(
            $indicatorIds,
            $cenHieDependencyId,
            $yearId,
        );

        $pendingIds = array_values(array_diff(
            $indicatorIds,
            $persisted->all(),
        ));

        if ($pendingIds === []) {
            return $persisted;
        }

        return $persisted
            ->merge($this->indicatorIdsWithAggregatedCalculationErrors(
                $pendingIds,
                $cenHieDependencyId,
                $yearId,
                $preloadedDesempenoBatch,
            ))
            ->unique()
            ->values();
    }

    public function hasErrorsForIndicator(
        int $indicatorId,
        int $cenHieDependencyId,
        int $yearId,
    ): bool {
        return $this->indicatorIdsWithCalculationErrors(
            [$indicatorId],
            $cenHieDependencyId,
            $yearId,
        )->isNotEmpty();
    }

    /**
     * @return array{message: string, center_count: int, period_count: int}|null
     */
    public function summaryForIndicator(
        int $indicatorId,
        int $cenHieDependencyId,
        int $yearId,
    ): ?array {
        $persistedSummary = $this->summaryFromPersistedResults(
            $indicatorId,
            $cenHieDependencyId,
            $yearId,
        );

        if ($persistedSummary !== null) {
            return $persistedSummary;
        }

        return $this->summaryFromAggregatedResults(
            $indicatorId,
            $cenHieDependencyId,
            $yearId,
        );
    }

    /**
     * @param  Collection<int, MetIndicator>  $indicators
     * @return list<array{severity: string, title: string, body: string, url: string}>
     */
    public function dashboardAlertsForIndicators(
        Collection $indicators,
        int $cenHieDependencyId,
        int $yearId,
    ): array {
        if ($indicators->isEmpty()) {
            return [];
        }

        $indicatorIds = $indicators->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $errorIds = $this->indicatorIdsWithCalculationErrors(
            $indicatorIds,
            $cenHieDependencyId,
            $yearId,
        )->flip();

        $alerts = [];

        foreach ($indicators as $indicator) {
            if (! isset($errorIds[(int) $indicator->id])) {
                continue;
            }

            $summary = $this->summaryForIndicator((int) $indicator->id, $cenHieDependencyId, $yearId);
            if ($summary === null) {
                continue;
            }

            $type = $indicator->indicatorType;
            if ($type === null) {
                continue;
            }

            $url = route('metas.instrument.indicators.show', [
                'indicatorType' => $type,
                'indicator' => $indicator,
            ]).'#tab-desempeno';

            $body = 'Error en resultados — '.$summary['message'];
            if ($summary['center_count'] > 1) {
                $body .= ' ('.$summary['center_count'].' establecimientos)';
            }

            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Metas: '.$indicator->clave,
                'body' => $body,
                'url' => $url,
            ];
        }

        return $alerts;
    }

    /**
     * Errores de cálculo persistidos indexados por clave de periodo para un establecimiento.
     *
     * @return array<string, string>
     */
    public function errorsByPeriodoForCenter(
        int $indicatorId,
        int $centerId,
        int $cenHieDependencyId,
        int $yearId,
        ?int $measurementUnitId = null,
    ): array {
        $query = $this->persistedCalculationErrorsQuery($cenHieDependencyId, $yearId)
            ->where('met_indicator_id', $indicatorId)
            ->where('cen_center_id', $centerId);

        if ($measurementUnitId !== null) {
            $query->where('met_measurement_unit_id', $measurementUnitId);
        } else {
            $query->whereNull('met_measurement_unit_id');
        }

        $errors = [];

        foreach ($query->get(['periodo', 'error_message']) as $row) {
            $message = (string) $row->error_message;

            if (! $this->storedMessageIsCalculationError($message)) {
                continue;
            }

            $errors[(string) $row->periodo] = $message;
        }

        return $errors;
    }

    /**
     * Errores de cálculo persistidos en `met_results` (sin agregación UI).
     *
     * @param  array<int, int>  $centerIds
     * @return Collection<int, array{count: int, periodos: array<int, string>, message: string}>
     */
    public function persistedErrorsByCenterId(
        int $indicatorId,
        int $cenHieDependencyId,
        int $yearId,
        array $centerIds,
    ): Collection {
        return $this->persistedErrorsByIndicators(
            [$indicatorId],
            $cenHieDependencyId,
            $yearId,
            $centerIds,
        )->get($indicatorId, collect());
    }

    /**
     * Errores persistidos por indicador × centro para un conjunto de indicadores (una query).
     *
     * @param  array<int, int>  $indicatorIds
     * @param  array<int, int>  $centerIds
     * @return Collection<int, Collection<int, array{count: int, periodos: array<int, string>, message: string}>>
     */
    public function persistedErrorsByIndicators(
        array $indicatorIds,
        int $cenHieDependencyId,
        int $yearId,
        array $centerIds,
    ): Collection {
        if ($indicatorIds === [] || $centerIds === []) {
            return collect();
        }

        $rows = $this->persistedCalculationErrorsQuery($cenHieDependencyId, $yearId)
            ->whereIn('met_indicator_id', $indicatorIds)
            ->whereIn('cen_center_id', $centerIds)
            ->orderBy('periodo')
            ->get(['met_indicator_id', 'cen_center_id', 'periodo', 'error_message']);

        if ($rows->isEmpty()) {
            return collect();
        }

        return $rows
            ->groupBy(fn ($row): int => (int) $row->met_indicator_id)
            ->mapWithKeys(function (Collection $indicatorRows, int|string $indicatorKey): array {
                $byCenter = $indicatorRows
                    ->groupBy(fn ($row): int => (int) $row->cen_center_id)
                    ->mapWithKeys(function (Collection $centerRows, int|string $centerKey): array {
                        $periodos = $centerRows->pluck('periodo')->unique()->values()->all();

                        return [
                            (int) $centerKey => [
                                'count' => count($periodos),
                                'periodos' => $periodos,
                                'message' => (string) $centerRows->first()->error_message,
                            ],
                        ];
                    });

                return [(int) $indicatorKey => $byCenter];
            });
    }

    /**
     * Errores de cálculo por establecimiento para un indicador.
     *
     * @param  array<int, int>  $centerIds
     * @return Collection<int, array{count: int, periodos: array<int, string>, message: string}>
     */
    public function errorsByCenterId(
        int $indicatorId,
        int $cenHieDependencyId,
        int $yearId,
        array $centerIds,
    ): Collection {
        if ($centerIds === []) {
            return collect();
        }

        $persisted = $this->persistedErrorsByCenterId(
            $indicatorId,
            $cenHieDependencyId,
            $yearId,
            $centerIds,
        );

        $indicator = MetIndicator::query()
            ->with(['dataFields', 'indicatorType'])
            ->find($indicatorId);

        if ($indicator === null) {
            return $persisted;
        }

        $aggregated = $this->aggregatedErrorsByCenterId($indicator, $cenHieDependencyId, $yearId, $centerIds);

        return $persisted
            ->union($aggregated)
            ->map(function (array $entry, int $centerId) use ($persisted, $aggregated): array {
                if ($persisted->has($centerId) && $aggregated->has($centerId)) {
                    $periodos = array_values(array_unique(array_merge(
                        $persisted[$centerId]['periodos'],
                        $aggregated[$centerId]['periodos'],
                    )));

                    return [
                        'count' => count($periodos),
                        'periodos' => $periodos,
                        'message' => $persisted[$centerId]['message'],
                    ];
                }

                return $entry;
            });
    }

    /**
     * @param  array<int, int>  $indicatorIds
     * @return Collection<int, int>
     */
    private function indicatorIdsWithPersistedCalculationErrors(
        array $indicatorIds,
        int $cenHieDependencyId,
        int $yearId,
    ): Collection {
        return $this->persistedCalculationErrorsQuery($cenHieDependencyId, $yearId)
            ->whereIn('met_indicator_id', $indicatorIds)
            ->distinct()
            ->pluck('met_indicator_id')
            ->map(fn ($id): int => (int) $id);
    }

    /**
     * @param  array<int, int>  $indicatorIds
     * @param  array<int, array{rows?: list<array<string, mixed>>}>|null  $preloadedDesempenoBatch
     * @return Collection<int, int>
     */
    private function indicatorIdsWithAggregatedCalculationErrors(
        array $indicatorIds,
        int $cenHieDependencyId,
        int $yearId,
        ?array $preloadedDesempenoBatch = null,
    ): Collection {
        if ($preloadedDesempenoBatch !== null) {
            return collect($indicatorIds)
                ->map(fn ($id): int => (int) $id)
                ->filter(function (int $indicatorId) use ($preloadedDesempenoBatch): bool {
                    $payload = $preloadedDesempenoBatch[$indicatorId] ?? ['rows' => []];

                    return $this->errorsFromDesempenoPayload($payload)->isNotEmpty();
                })
                ->values();
        }

        $indicators = MetIndicator::query()
            ->whereIn('id', $indicatorIds)
            ->with(['dataFields', 'indicatorType'])
            ->get();

        if ($indicators->isEmpty()) {
            return collect();
        }

        $payloads = $this->desempenoByEstablishmentService->buildBatchForIndicators(
            $indicators,
            $cenHieDependencyId,
            $yearId,
        );

        return $indicators
            ->filter(function (MetIndicator $indicator) use ($payloads): bool {
                $payload = $payloads[$indicator->id] ?? ['rows' => []];

                return $this->errorsFromDesempenoPayload($payload)->isNotEmpty();
            })
            ->map(fn (MetIndicator $indicator): int => (int) $indicator->id)
            ->values();
    }

    /**
     * @return array{message: string, center_count: int, period_count: int}|null
     */
    private function summaryFromPersistedResults(
        int $indicatorId,
        int $cenHieDependencyId,
        int $yearId,
    ): ?array {
        $rows = $this->persistedCalculationErrorsQuery($cenHieDependencyId, $yearId)
            ->where('met_indicator_id', $indicatorId)
            ->get(['cen_center_id', 'periodo', 'error_message']);

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'message' => (string) $rows->first()->error_message,
            'center_count' => $rows->pluck('cen_center_id')->unique()->count(),
            'period_count' => $rows->pluck('periodo')->unique()->count(),
        ];
    }

    /**
     * @return array{message: string, center_count: int, period_count: int}|null
     */
    private function summaryFromAggregatedResults(
        int $indicatorId,
        int $cenHieDependencyId,
        int $yearId,
    ): ?array {
        $indicator = MetIndicator::query()
            ->with(['dataFields', 'indicatorType'])
            ->find($indicatorId);

        if ($indicator === null) {
            return null;
        }

        $errorsByCenter = $this->aggregatedErrorsByCenterId($indicator, $cenHieDependencyId, $yearId);

        if ($errorsByCenter->isEmpty()) {
            return null;
        }

        $first = $errorsByCenter->first();

        return [
            'message' => (string) $first['message'],
            'center_count' => $errorsByCenter->count(),
            'period_count' => $errorsByCenter->sum(fn (array $entry): int => $entry['count']),
        ];
    }

    /**
     * @param  array<int, int>  $centerIds
     * @return Collection<int, array{count: int, periodos: array<int, string>, message: string}>
     */
    private function aggregatedErrorsByCenterId(
        MetIndicator $indicator,
        int $cenHieDependencyId,
        int $yearId,
        array $centerIds = [],
    ): Collection {
        $payload = $this->desempenoByEstablishmentService->buildForIndicator(
            $indicator,
            $cenHieDependencyId,
            $yearId,
        );

        return $this->errorsFromDesempenoPayload($payload, $centerIds);
    }

    /**
     * @param  array{rows?: list<array<string, mixed>>}  $payload
     * @param  array<int, int>  $centerIds
     * @return Collection<int, array{count: int, periodos: array<int, string>, message: string}>
     */
    private function errorsFromDesempenoPayload(array $payload, array $centerIds = []): Collection
    {
        return collect($payload['rows'] ?? [])
            ->filter(function (array $row) use ($centerIds): bool {
                if ($centerIds !== [] && ! in_array((int) $row['cen_center_id'], $centerIds, true)) {
                    return false;
                }

                return $this->storedMessageIsCalculationError($row['error'] ?? null);
            })
            ->mapWithKeys(function (array $row): array {
                $periodo = filled($row['ultimo_periodo'] ?? null)
                    ? (string) $row['ultimo_periodo']
                    : 'ANUAL';

                return [
                    (int) $row['cen_center_id'] => [
                        'count' => 1,
                        'periodos' => [$periodo],
                        'message' => (string) $row['error'],
                    ],
                ];
            });
    }

    private function storedMessageIsCalculationError(?string $message): bool
    {
        return filled($message)
            && ! MetResult::storedErrorMessageIsNonCalculationAlert($message);
    }

    private function persistedCalculationErrorsQuery(int $cenHieDependencyId, int $yearId): Builder
    {
        return MetResult::query()
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->where('year_id', $yearId)
            ->whereNotNull('error_message')
            ->whereNotIn('error_message', ['N/D', MetResult::MESSAGE_SIN_CASOS]);
    }
}
