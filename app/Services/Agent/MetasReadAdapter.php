<?php

namespace App\Services\Agent;

use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\MetResult;
use App\Models\Year;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MetasReadAdapter
{
    public const INSTRUMENTO = 'metas';

    /** @var list<string> */
    private const LEY_ROUTE_PREFIXES = [
        '18834' => 'metas-18834',
        '19664' => 'metas-19664',
        '20707' => 'metas-20707',
    ];

    /**
     * @return LengthAwarePaginator<int, MetIndicator>
     */
    public function paginateIndicators(Year $year, ?string $ley, ?string $q, int $perPage): LengthAwarePaginator
    {
        $typeIds = $this->indicatorTypeIdsForLey($ley);

        $query = MetIndicator::query()
            ->where('year_id', $year->id)
            ->whereIn('indicator_type_id', $typeIds)
            ->with(['indicatorType:id,clave_indicador,nombre_indicador,route'])
            ->orderBy('orden')
            ->orderBy('clave');

        if ($q !== null && $q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $b) use ($like): void {
                $b->where('clave', 'like', $like)
                    ->orWhere('nombre', 'like', $like);
            });
        }

        return $query->paginate($perPage);
    }

    public function findIndicator(Year $year, int $id): ?MetIndicator
    {
        return MetIndicator::query()
            ->whereKey($id)
            ->where('year_id', $year->id)
            ->with(['indicatorType:id,clave_indicador,nombre_indicador,route'])
            ->first();
    }

    /**
     * @return LengthAwarePaginator<int, MetResult>
     */
    public function paginateResults(Year $year, int $ssId, int $indicatorId, int $perPage, int $page = 1): LengthAwarePaginator
    {
        return MetResult::query()
            ->where('year_id', $year->id)
            ->where('cen_hie_dependency_id', $ssId)
            ->where('met_indicator_id', $indicatorId)
            ->with(['center:id,codigo,nombre_oficial,nombre_corto', 'indicator:id,clave,nombre'])
            ->orderBy('cen_center_id')
            ->orderBy('periodo')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array<string, mixed>
     */
    public function mapListItem(MetIndicator $indicator): array
    {
        $ley = $this->leyFromRoute($indicator->indicatorType?->route);

        return [
            'id' => $indicator->id,
            'instrumento' => self::INSTRUMENTO,
            'ley' => $ley,
            'clave' => $indicator->clave,
            'nombre' => $indicator->nombre,
            'indicator_type_id' => $indicator->indicator_type_id,
            'orden' => $indicator->orden,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapDetail(MetIndicator $indicator): array
    {
        return array_merge($this->mapListItem($indicator), [
            'objetivo' => $indicator->objetivo,
            'meta_valor' => $indicator->meta_valor,
            'meta_operador' => $indicator->meta_operador?->value ?? $indicator->meta_operador,
            'unidad_medida' => $indicator->unidad_medida,
            'estado' => (bool) $indicator->estado,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function mapResult(MetResult $result): array
    {
        return [
            'id' => $result->id,
            'instrumento' => self::INSTRUMENTO,
            'indicador_id' => $result->met_indicator_id,
            'cen_center_id' => $result->cen_center_id,
            'establecimiento' => $result->center?->nombre_oficial ?? $result->center?->nombre_corto,
            'cen_hie_dependency_id' => $result->cen_hie_dependency_id,
            'periodo' => $result->periodo,
            'resultado' => $result->resultado,
            'cumple_meta' => $result->cumple_meta,
            'etapa' => $result->etapa,
        ];
    }

    /**
     * @return list<int>
     */
    private function indicatorTypeIdsForLey(?string $ley): array
    {
        $routes = $this->activeTypeRoutes();

        if ($ley === null || $ley === '') {
            return $routes->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $normalized = $this->normalizedLey($ley);
        if ($normalized === null) {
            return [];
        }

        $route = self::LEY_ROUTE_PREFIXES[$normalized];

        return $routes->where('route', $route)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Indica si el código de ley pertenece al catálogo de leyes Metas admitidas.
     */
    public function supportsLey(?string $ley): bool
    {
        if ($ley === null || $ley === '') {
            return true;
        }

        return $this->normalizedLey($ley) !== null;
    }

    /**
     * Indicadores Metas del año para una ley admitida (0 si el tipo no está activo).
     */
    public function countIndicatorsForLey(Year $year, string $ley): int
    {
        $typeIds = $this->indicatorTypeIdsForLey($ley);
        if ($typeIds === []) {
            return 0;
        }

        return MetIndicator::query()
            ->where('year_id', $year->id)
            ->whereIn('indicator_type_id', $typeIds)
            ->count();
    }

    /**
     * @return Collection<int, object{id: int|string, route: string|null}>
     */
    private function activeTypeRoutes(): Collection
    {
        return IndicatorType::query()
            ->where('estado', true)
            ->whereIn('route', array_values(self::LEY_ROUTE_PREFIXES))
            ->get(['id', 'route']);
    }

    private function normalizedLey(?string $ley): ?string
    {
        if ($ley === null) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $ley) ?? '';

        return array_key_exists($normalized, self::LEY_ROUTE_PREFIXES) ? $normalized : null;
    }

    private function leyFromRoute(?string $route): ?string
    {
        if ($route === null) {
            return null;
        }

        foreach (self::LEY_ROUTE_PREFIXES as $ley => $slug) {
            if ($route === $slug) {
                return $ley;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, array{code: string, label: string}>
     */
    public function availableLeyes(): Collection
    {
        return collect([
            ['code' => '18834', 'label' => 'Ley 18.834'],
            ['code' => '19664', 'label' => 'Ley 19.664'],
            ['code' => '20707', 'label' => 'Ley 20.707'],
        ]);
    }
}
