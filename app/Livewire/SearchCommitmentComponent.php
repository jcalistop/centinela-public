<?php

namespace App\Livewire;

use App\Services\Comges\ComgesCommitmentAlertPresenter;
use App\Services\Comges\ComgesCommitmentSearchService;
use App\Services\Indicators\IndicatorAmbitoAssignmentService;
use App\Support\Comges\ComgesBrowseContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;

class SearchCommitmentComponent extends Component
{
    public string $search = '';

    public string $ambitoFilter = '';

    private ?Collection $cachedAmbitoOptions = null;

    public function render(): View
    {
        $context = ComgesBrowseContext::fromSession($this->search, $this->ambitoFilter);

        $commitments = app(ComgesCommitmentSearchService::class)->search($context);
        $alertGroups = app(ComgesCommitmentAlertPresenter::class)->forBrowse($context, $commitments);

        return view('livewire.search-commitment-component', [
            'commitments' => $commitments,
            'useScoreScale' => $context->useScoreScale,
            'isSearching' => $context->isSearching(),
            'isFiltering' => $context->isFiltering(),
            'userRoleName' => $context->userRoleName,
            'alertGroups' => $alertGroups,
            'yearId' => $context->yearId,
            'indicatorAmbitoOptions' => $this->getAmbitoOptions(),
        ]);
    }

    private function getAmbitoOptions(): Collection
    {
        return $this->cachedAmbitoOptions ??= app(IndicatorAmbitoAssignmentService::class)->activeCatalog();
    }
}
