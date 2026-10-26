<?php

namespace App\Services\Metas;

use App\Models\CenCenter;
use App\Models\MetCenterAdosado;
use App\Models\MetCenterInclusion;
use App\Models\MetIndicatorConfiguration;
use Illuminate\Support\Collection;

class MetEstablishmentIndicatorLoadability
{
    /**
     * @param  Collection<int>|array<int>  $motherApplicableCenterIds  Centros madre con `aplica = true` para el indicador
     * @return Collection<int>
     */
    public function allowedEstablishmentCenterIdsIncludingAdosados(
        Collection|array $motherApplicableCenterIds,
        int $yearId,
        int $cenHieDependencyId,
        int $indicatorTypeId,
    ): Collection {
        $mothers = collect($motherApplicableCenterIds)->unique()->values();

        if ($mothers->isEmpty()) {
            return collect();
        }

        $adosadoIds = MetCenterAdosado::query()
            ->forInstrument($indicatorTypeId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->whereIn('cen_center_madre_id', $mothers)
            ->pluck('cen_center_adosado_id');

        return $mothers->concat($adosadoIds)->unique()->values();
    }

    /**
     * Centro incluido en la medición o adosado cuya madre está incluida (mismo instrumento, año y SS).
     */
    public function centerIsInMeasurementOrAdosadoOfIncludedMother(
        CenCenter $center,
        int $yearId,
        int $indicatorTypeId,
        int $cenHieDependencyId,
    ): bool {
        if (MetCenterInclusion::query()
            ->where('cen_center_id', $center->id)
            ->where('year_id', $yearId)
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->exists()
        ) {
            return true;
        }

        $madreId = MetCenterAdosado::query()
            ->forInstrument($indicatorTypeId)
            ->where('cen_center_adosado_id', $center->id)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->value('cen_center_madre_id');

        if ($madreId === null) {
            return false;
        }

        return MetCenterInclusion::query()
            ->where('cen_center_id', $madreId)
            ->where('year_id', $yearId)
            ->where('indicator_type_id', $indicatorTypeId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->exists();
    }

    /**
     * Aplicabilidad efectiva para carga a nivel establecimiento (sin unidad de medición).
     * Si el centro es adosado en `met_center_adosados` y no tiene fila propia en configuración, hereda la de la madre.
     */
    public function establishmentIndicatorAplicaForCenter(
        int $metIndicatorId,
        CenCenter $center,
        int $yearId,
        int $cenHieDependencyId,
        int $indicatorTypeId,
    ): bool {
        $aplica = MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $metIndicatorId)
            ->where('cen_center_id', $center->id)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->whereNull('met_measurement_unit_id')
            ->value('aplica');

        if ($aplica !== null) {
            return (bool) $aplica;
        }

        $madreId = MetCenterAdosado::query()
            ->forInstrument($indicatorTypeId)
            ->where('cen_center_adosado_id', $center->id)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->value('cen_center_madre_id');

        if ($madreId === null) {
            return true;
        }

        $motherAplica = MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $metIndicatorId)
            ->where('cen_center_id', $madreId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->whereNull('met_measurement_unit_id')
            ->value('aplica');

        return $motherAplica === null || (bool) $motherAplica;
    }

    /**
     * @param  Collection<int, CenCenter>  $centers
     * @param  Collection<int>  $mothersInScopeOrderedIds  Orden de madres (p. ej. el del listado base)
     * @param  Collection<int>  $allowedCenterIds
     * @return Collection<int, CenCenter>
     */
    public function orderCentersMotherThenAdosados(
        Collection $centers,
        Collection $mothersInScopeOrderedIds,
        Collection $allowedCenterIds,
        int $yearId,
        int $cenHieDependencyId,
        int $indicatorTypeId,
    ): Collection {
        if ($centers->isEmpty()) {
            return $centers;
        }

        /** @var Collection<int, CenCenter> $byId */
        $byId = $centers->keyBy('id');

        $ordered = collect();

        foreach ($mothersInScopeOrderedIds as $motherId) {
            $motherId = (int) $motherId;
            $mother = $byId->get($motherId);
            if ($mother instanceof CenCenter) {
                $ordered->push($mother);
            }

            $adosadoIds = MetCenterAdosado::query()
                ->forInstrument($indicatorTypeId)
                ->where('cen_center_madre_id', $motherId)
                ->where('year_id', $yearId)
                ->where('cen_hie_dependency_id', $cenHieDependencyId)
                ->with('centerAdosado:id,nombre_corto')
                ->get()
                ->sortBy(fn (MetCenterAdosado $r): string => (string) ($r->centerAdosado?->nombre_corto ?? ''))
                ->pluck('cen_center_adosado_id')
                ->filter(fn (int|string $id): bool => $allowedCenterIds->contains((int) $id));

            foreach ($adosadoIds as $adosadoId) {
                $adosadoId = (int) $adosadoId;
                $child = $byId->get($adosadoId);
                if ($child instanceof CenCenter) {
                    $ordered->push($child);
                }
            }
        }

        return $ordered->values();
    }

    /**
     * @return array<int, array{madre_codigo: string|null, madre_nombre: string|null}>
     */
    public function adosadoMadreMetaByAdosadoCenterId(
        Collection $adosadoCenterIds,
        int $yearId,
        int $cenHieDependencyId,
        int $indicatorTypeId,
    ): array {
        if ($adosadoCenterIds->isEmpty()) {
            return [];
        }

        $rows = MetCenterAdosado::query()
            ->forInstrument($indicatorTypeId)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $cenHieDependencyId)
            ->whereIn('cen_center_adosado_id', $adosadoCenterIds)
            ->with(['centerMadre:id,codigo,nombre_corto'])
            ->get();

        $meta = [];

        foreach ($rows as $row) {
            $meta[(int) $row->cen_center_adosado_id] = [
                'madre_codigo' => $row->centerMadre?->codigo,
                'madre_nombre' => $row->centerMadre?->nombre_corto,
            ];
        }

        return $meta;
    }
}
