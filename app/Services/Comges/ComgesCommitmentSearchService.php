<?php

namespace App\Services\Comges;

use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Support\Comges\ComgesBrowseContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ComgesCommitmentSearchService
{
    /**
     * @return Collection<int, ComCommitment>
     */
    public function search(ComgesBrowseContext $context): Collection
    {
        if ($context->yearId === null) {
            return collect();
        }

        $commitments = ComCommitment::query()
            ->where('year_id', $context->yearId)
            ->when($context->isSearching(), fn (Builder $query) => $this->applySearchFilter($query, $context->search))
            ->with($this->eagerLoads($context))
            ->orderBy('orden')
            ->get();

        if ($context->isSearching()) {
            $this->pruneIndicatorsForSearch($commitments, $context->search);
        }

        if ($context->hasAmbitoFilter()) {
            $this->pruneIndicatorsForAmbito($commitments, $context->ambitoFilter);
        }

        return $commitments
            ->filter(fn (ComCommitment $commitment): bool => ! $context->isSearching() || $commitment->comIndicators->isNotEmpty())
            ->filter(fn (ComCommitment $commitment): bool => ! $context->hasAmbitoFilter() || $commitment->comIndicators->isNotEmpty())
            ->filter(fn (ComCommitment $commitment): bool => $this->isVisible($commitment, $context))
            ->values();
    }

    /**
     * @param  Collection<int, ComCommitment>  $commitments
     */
    private function pruneIndicatorsForAmbito(Collection $commitments, string $ambitoFilter): void
    {
        foreach ($commitments as $commitment) {
            $filtered = $commitment->comIndicators->filter(
                fn (ComIndicator $indicator): bool => $this->indicatorMatchesAmbito($indicator, $ambitoFilter),
            );

            $commitment->setRelation('comIndicators', $filtered->values());
        }
    }

    private function indicatorMatchesAmbito(ComIndicator $indicator, string $ambitoFilter): bool
    {
        if ($ambitoFilter === 'none') {
            return $indicator->relationLoaded('indicatorAmbitos')
                ? $indicator->indicatorAmbitos->isEmpty()
                : ! $indicator->indicatorAmbitos()->exists();
        }

        if (is_numeric($ambitoFilter)) {
            $ambitoId = (int) $ambitoFilter;

            return $indicator->relationLoaded('indicatorAmbitos')
                ? $indicator->indicatorAmbitos->contains('id', $ambitoId)
                : $indicator->indicatorAmbitos()->where('indicator_ambitos.id', $ambitoId)->exists();
        }

        return true;
    }

    /**
     * Si el compromiso entró por un indicador hijo, el eager load trae todos los indicadores;
     * aquí se dejan solo los que coinciden con el texto (salvo que el compromiso coincida por clave/nombre).
     *
     * @param  Collection<int, ComCommitment>  $commitments
     */
    private function pruneIndicatorsForSearch(Collection $commitments, string $search): void
    {
        $needle = mb_strtolower($search);

        foreach ($commitments as $commitment) {
            if ($this->commitmentMatchesSearch($commitment, $needle)) {
                continue;
            }

            $filtered = $commitment->comIndicators->filter(
                fn (ComIndicator $indicator): bool => $this->indicatorMatchesSearch($indicator, $needle),
            );

            $commitment->setRelation('comIndicators', $filtered->values());
        }
    }

    private function commitmentMatchesSearch(ComCommitment $commitment, string $needleLower): bool
    {
        return str_contains(mb_strtolower((string) $commitment->clave), $needleLower)
            || str_contains(mb_strtolower((string) $commitment->nombre), $needleLower);
    }

    private function indicatorMatchesSearch(ComIndicator $indicator, string $needleLower): bool
    {
        return str_contains(mb_strtolower((string) $indicator->clave), $needleLower)
            || str_contains(mb_strtolower((string) $indicator->nombre), $needleLower);
    }

    private function applySearchFilter(Builder $query, string $search): void
    {
        $query->where(function (Builder $q) use ($search): void {
            $q->where('clave', 'like', "%{$search}%")
                ->orWhere('nombre', 'like', "%{$search}%")
                ->orWhereHas('comIndicators', function (Builder $indicatorQuery) use ($search): void {
                    $indicatorQuery->where('clave', 'like', "%{$search}%")
                        ->orWhere('nombre', 'like', "%{$search}%");
                });
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function eagerLoads(ComgesBrowseContext $context): array
    {
        $loads = [
            'comIndicators' => fn ($query) => $query->orderBy('orden')->orderBy('clave'),
            'comIndicators.indicatorAmbitos',
            'comIndicators.comIndicatorCutoffs.cutoff',
            'comIndicators.comIndicatorCutoffs.comActions.comEvaluations' => function ($query) use ($context): void {
                if ($context->cenHieDependencyId !== null) {
                    $query->where('cen_hie_dependency_id', $context->cenHieDependencyId);
                }
            },
        ];

        if (! $context->useScoreScale || $context->isFiltering()) {
            return $loads;
        }

        $loads['comIndicators.comIndicatorCutoffs.comActions.comSensitivityTables.comSensitivityDependencies'] = [];

        if ($context->cenHieDependencyId !== null) {
            $loads['comIndicators.comIndicatorCutoffs.comActions.comVerifiableDocuments'] = function ($query) use ($context): void {
                $query->where('cen_hie_dependency_id', $context->cenHieDependencyId);
            };
        }

        return $loads;
    }

    private function isVisible(ComCommitment $commitment, ComgesBrowseContext $context): bool
    {
        if ($context->hasAmbitoFilter() && $commitment->comIndicators->isEmpty()) {
            return false;
        }

        if (! $context->useScoreScale) {
            return true;
        }

        return $commitment->comIndicators->count() > 0 || $context->showsEmptyCommitments();
    }
}
