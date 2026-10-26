<?php

namespace App\Services\Metas;

use App\Models\MetCenterAdosado;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use Illuminate\Support\Collection;

class MetIndicatorEstablishmentsOverviewService
{
    /**
     * @return array{
     *     uses_internal_unit_measurement: bool,
     *     rows: list<array{
     *         cen_center_id: int,
     *         codigo: string,
     *         nombre: string,
     *         aplica: bool,
     *         convenido: bool,
     *         agrupa_ss: bool,
     *         adosados: list<string>
     *     }>
     * }
     */
    public function buildForIndicator(MetIndicator $indicator, int $dependencyId, int $yearId): array
    {
        $indicator->loadMissing('indicatorType');

        $usesInternalUnitMeasurement = (bool) $indicator->indicatorType?->usesInternalUnitMeasurement();

        $configsQuery = MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $indicator->id)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->with('center:id,nombre_corto,codigo');

        if (! $usesInternalUnitMeasurement) {
            $configsQuery->whereNull('met_measurement_unit_id');
        }

        $configs = $configsQuery->get();

        if ($usesInternalUnitMeasurement) {
            $rows = $configs
                ->groupBy('cen_center_id')
                ->map(function (Collection $rows): array {
                    $first = $rows->first();
                    $centerId = (int) $first->cen_center_id;

                    return [
                        'cen_center_id' => $centerId,
                        'nombre' => optional($first->center)->nombre_corto ?? '—',
                        'codigo' => optional($first->center)->codigo ?? '',
                        'aplica' => $rows->contains(fn ($x) => (bool) ($x->aplica ?? true)),
                        'convenido' => $rows->contains(fn ($x) => (bool) $x->seleccionado),
                        'agrupa_ss' => $rows->contains(fn ($x) => (bool) ($x->agrupa_ss ?? false)),
                        'adosados' => [],
                    ];
                })
                ->sortBy('nombre')
                ->values()
                ->all();
        } else {
            $rows = $configs->map(fn (MetIndicatorConfiguration $c): array => [
                'cen_center_id' => (int) $c->cen_center_id,
                'nombre' => optional($c->center)->nombre_corto ?? '—',
                'codigo' => optional($c->center)->codigo ?? '',
                'aplica' => (bool) ($c->aplica ?? true),
                'convenido' => (bool) $c->seleccionado,
                'agrupa_ss' => (bool) ($c->agrupa_ss ?? false),
                'adosados' => [],
            ])->sortBy('nombre')->values()->all();

            $rows = $this->attachAdosadoNames(
                $rows,
                (int) $indicator->indicator_type_id,
                $dependencyId,
                $yearId,
            );
        }

        return [
            'uses_internal_unit_measurement' => $usesInternalUnitMeasurement,
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function attachAdosadoNames(
        array $rows,
        int $indicatorTypeId,
        int $dependencyId,
        int $yearId,
    ): array {
        $centerIds = collect($rows)->pluck('cen_center_id')->filter()->all();

        if ($centerIds === []) {
            return $rows;
        }

        $adosadoNamesByMadreId = MetCenterAdosado::query()
            ->forInstrument($indicatorTypeId)
            ->whereIn('cen_center_madre_id', $centerIds)
            ->where('year_id', $yearId)
            ->where('cen_hie_dependency_id', $dependencyId)
            ->with('centerAdosado:id,nombre_corto')
            ->get()
            ->groupBy('cen_center_madre_id')
            ->map(
                fn (Collection $group) => $group
                    ->map(fn (MetCenterAdosado $r) => optional($r->centerAdosado)->nombre_corto)
                    ->filter()
                    ->values()
                    ->all()
            );

        return collect($rows)
            ->map(function (array $row) use ($adosadoNamesByMadreId): array {
                $row['adosados'] = $adosadoNamesByMadreId->get($row['cen_center_id'], []);

                return $row;
            })
            ->all();
    }
}
