<?php

namespace App\Services\Indicators;

use App\Models\Concerns\HasIndicatorAmbitos;
use App\Models\IndicatorAmbito;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class IndicatorAmbitoAssignmentService
{
    /**
     * @param  list<int|string>  $rawIds
     */
    public function sync(Model $indicatable, array $rawIds): void
    {
        if (! in_array(HasIndicatorAmbitos::class, class_uses_recursive($indicatable), true)) {
            return;
        }
        $ids = $this->normalizeIds($rawIds);

        if ($ids === []) {
            $indicatable->indicatorAmbitos()->detach();

            return;
        }

        $validIds = IndicatorAmbito::query()
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $indicatable->indicatorAmbitos()->sync($validIds);
    }

    /**
     * @return list<int>
     */
    public function idsFromRequest(Request $request): array
    {
        $raw = $request->input('indicator_ambito_ids', []);

        if (! is_array($raw)) {
            return [];
        }

        return $this->normalizeIds($raw);
    }

    /**
     * @return Collection<int, IndicatorAmbito>
     */
    public function activeCatalog(): Collection
    {
        return IndicatorAmbito::query()->activo()->ordered()->get();
    }

    public function applyToQuery(Builder $query, string $ambitoFilter): Builder
    {
        if ($ambitoFilter === 'none') {
            return $query->whereDoesntHave('indicatorAmbitos');
        }

        if ($ambitoFilter !== '' && is_numeric($ambitoFilter)) {
            $ambitoId = (int) $ambitoFilter;

            return $query->whereHas(
                'indicatorAmbitos',
                fn (Builder $sub) => $sub->where('indicator_ambitos.id', $ambitoId)
            );
        }

        return $query;
    }

    /**
     * @param  list<int|string>  $rawIds
     * @return list<int>
     */
    private function normalizeIds(array $rawIds): array
    {
        return collect($rawIds)
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
