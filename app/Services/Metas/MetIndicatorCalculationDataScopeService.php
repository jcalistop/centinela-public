<?php

namespace App\Services\Metas;

use App\Models\MetCenterAdosado;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;

class MetIndicatorCalculationDataScopeService
{
    /**
     * Memo request-scoped: adosados por instrumento+año+SS (madre_id => lista de adosados).
     *
     * @var array<string, array<int, list<int>>>
     */
    private array $adosadosByMadreMemo = [];

    /**
     * Memo request-scoped: centros con aplica=true por indicador (sin filtrar el centro presentado).
     *
     * @var array<string, array<int, list<int>>>
     */
    private array $aplicaCenterIdsByKey = [];

    public function __construct(
        private readonly MetEstablishmentIndicatorLoadability $loadability,
    ) {}

    /**
     * IDs adicionales al centro madre para agregación (adosados manuales + SS aplicables si `agrupa_ss`).
     *
     * @return array<int, int>
     */
    public function additionalDataCenterIds(
        MetIndicator $indicator,
        int $centerId,
        MetIndicatorConfiguration $config,
        int $yearId,
        int $cenHieDependencyId,
        ?int $measurementUnitId = null,
    ): array {
        return array_values(array_unique(array_merge(
            $this->manualAdosadoCenterIds($indicator, $centerId, $yearId, $cenHieDependencyId),
            $this->agrupaSsExtraCenterIds(
                $indicator,
                $centerId,
                $config,
                $yearId,
                $cenHieDependencyId,
                $measurementUnitId,
            ),
        )));
    }

    /**
     * Universo completo de centros cuyos `met_monthly_data` alimentan cálculo o estado de carga.
     *
     * @return array<int, int>
     */
    public function allDataCenterIds(
        MetIndicator $indicator,
        int $centerId,
        MetIndicatorConfiguration $config,
        int $yearId,
        int $cenHieDependencyId,
        ?int $measurementUnitId = null,
    ): array {
        return array_values(array_unique(array_merge(
            [$centerId],
            $this->additionalDataCenterIds(
                $indicator,
                $centerId,
                $config,
                $yearId,
                $cenHieDependencyId,
                $measurementUnitId,
            ),
        )));
    }

    /**
     * @return array<int, int>
     */
    public function manualAdosadoCenterIds(
        MetIndicator $indicator,
        int $centerId,
        int $yearId,
        int $cenHieDependencyId,
    ): array {
        $indicator->loadMissing('indicatorType');

        if ($indicator->indicatorType?->usesInternalUnitMeasurement()) {
            return [];
        }

        $map = $this->adosadosByMadreMap(
            (int) $indicator->indicator_type_id,
            $yearId,
            $cenHieDependencyId,
        );

        return $map[$centerId] ?? [];
    }

    /**
     * Precarga adosados del instrumento+año+SS (una sola query). Idempotente.
     */
    public function preloadAdosadosForInstrument(int $indicatorTypeId, int $yearId, int $cenHieDependencyId): void
    {
        $this->adosadosByMadreMap($indicatorTypeId, $yearId, $cenHieDependencyId);
    }

    public function forgetAdosados(?int $indicatorTypeId = null, ?int $yearId = null, ?int $cenHieDependencyId = null): void
    {
        if ($indicatorTypeId === null || $yearId === null || $cenHieDependencyId === null) {
            $this->adosadosByMadreMemo = [];
            $this->aplicaCenterIdsByKey = [];

            return;
        }

        unset($this->adosadosByMadreMemo[$this->adosadosMemoKey($indicatorTypeId, $yearId, $cenHieDependencyId)]);
    }

    /**
     * Precarga centros `aplica=true` para un set de indicadores (una query). Idempotente.
     *
     * @param  array<int, int>  $indicatorIds
     */
    public function preloadAplicaCentersForIndicators(
        array $indicatorIds,
        int $yearId,
        int $dependencyId,
        ?int $measurementUnitId = null,
    ): void {
        $this->ensureAplicaCenterIdsLoaded($indicatorIds, $yearId, $dependencyId, $measurementUnitId);
    }

    /**
     * Centros del SS con `aplica=true` (y sus adosados) cuando el centro presentado tiene `agrupa_ss`.
     *
     * @return array<int, int>
     */
    public function agrupaSsExtraCenterIds(
        MetIndicator $indicator,
        int $centerId,
        MetIndicatorConfiguration $config,
        int $yearId,
        int $cenHieDependencyId,
        ?int $measurementUnitId = null,
    ): array {
        if (! $config->agrupa_ss) {
            return [];
        }

        $byIndicator = $this->resolveSsAplicableIdsByIndicator(
            [(int) $indicator->id],
            $centerId,
            $yearId,
            $cenHieDependencyId,
            $measurementUnitId,
            (int) $indicator->indicator_type_id,
        );

        return $byIndicator[(int) $indicator->id] ?? [];
    }

    /**
     * @param  array<int, int>  $agrupaSsIndicatorIds
     * @return array<int, array<int, int>>
     */
    public function resolveSsAplicableIdsByIndicator(
        array $agrupaSsIndicatorIds,
        int $centerId,
        int $yearId,
        int $dependencyId,
        ?int $measurementUnitId,
        int $indicatorTypeId,
    ): array {
        $ssAplicableIdsByIndicator = [];

        if ($agrupaSsIndicatorIds === []) {
            return $ssAplicableIdsByIndicator;
        }

        $this->preloadAdosadosForInstrument($indicatorTypeId, $yearId, $dependencyId);
        $this->ensureAplicaCenterIdsLoaded($agrupaSsIndicatorIds, $yearId, $dependencyId, $measurementUnitId);

        $adosadosByMadre = $this->adosadosByMadreMap($indicatorTypeId, $yearId, $dependencyId);
        $memoKey = $this->aplicaCentersMemoKey($yearId, $dependencyId, $measurementUnitId);

        foreach ($agrupaSsIndicatorIds as $indicatorId) {
            $indicatorId = (int) $indicatorId;
            $motherIds = array_values(array_filter(
                $this->aplicaCenterIdsByKey[$memoKey][$indicatorId] ?? [],
                fn (int $aplicaCenterId): bool => $aplicaCenterId !== $centerId,
            ));

            $extra = [];
            foreach ($motherIds as $motherId) {
                $extra[] = $motherId;
                foreach ($adosadosByMadre[$motherId] ?? [] as $adosadoId) {
                    $extra[] = $adosadoId;
                }
            }

            $ssAplicableIdsByIndicator[$indicatorId] = array_values(array_unique($extra));
        }

        return $ssAplicableIdsByIndicator;
    }

    /**
     * @param  array<int, int>  $indicatorIds
     */
    private function ensureAplicaCenterIdsLoaded(
        array $indicatorIds,
        int $yearId,
        int $dependencyId,
        ?int $measurementUnitId,
    ): void {
        $indicatorIds = array_values(array_unique(array_map('intval', $indicatorIds)));
        if ($indicatorIds === []) {
            return;
        }

        $memoKey = $this->aplicaCentersMemoKey($yearId, $dependencyId, $measurementUnitId);
        $this->aplicaCenterIdsByKey[$memoKey] ??= [];

        $missing = array_values(array_filter(
            $indicatorIds,
            fn (int $id): bool => ! array_key_exists($id, $this->aplicaCenterIdsByKey[$memoKey]),
        ));

        if ($missing === []) {
            return;
        }

        foreach ($missing as $id) {
            $this->aplicaCenterIdsByKey[$memoKey][$id] = [];
        }

        $rows = MetIndicatorConfiguration::query()
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->whereIn('met_indicator_id', $missing)
            ->where('aplica', true)
            ->when(
                $measurementUnitId !== null,
                fn ($q) => $q->where('met_measurement_unit_id', $measurementUnitId),
                fn ($q) => $q->whereNull('met_measurement_unit_id'),
            )
            ->get(['met_indicator_id', 'cen_center_id']);

        foreach ($rows as $row) {
            $indicatorId = (int) $row->met_indicator_id;
            $this->aplicaCenterIdsByKey[$memoKey][$indicatorId][] = (int) $row->cen_center_id;
        }
    }

    private function aplicaCentersMemoKey(int $yearId, int $dependencyId, ?int $measurementUnitId): string
    {
        return $yearId.'|'.$dependencyId.'|'.($measurementUnitId ?? 'null');
    }

    /**
     * @return array<int, list<int>>
     */
    private function adosadosByMadreMap(int $indicatorTypeId, int $yearId, int $cenHieDependencyId): array
    {
        $key = $this->adosadosMemoKey($indicatorTypeId, $yearId, $cenHieDependencyId);

        if (array_key_exists($key, $this->adosadosByMadreMemo)) {
            return $this->adosadosByMadreMemo[$key];
        }

        $map = [];

        $rows = MetCenterAdosado::query()
            ->forInstrument($indicatorTypeId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->get(['cen_center_madre_id', 'cen_center_adosado_id']);

        foreach ($rows as $row) {
            $madreId = (int) $row->cen_center_madre_id;
            $map[$madreId][] = (int) $row->cen_center_adosado_id;
        }

        $this->adosadosByMadreMemo[$key] = $map;

        return $map;
    }

    private function adosadosMemoKey(int $indicatorTypeId, int $yearId, int $cenHieDependencyId): string
    {
        return $indicatorTypeId.'|'.$yearId.'|'.$cenHieDependencyId;
    }
}
