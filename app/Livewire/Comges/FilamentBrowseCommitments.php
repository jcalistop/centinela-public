<?php

namespace App\Livewire\Comges;

use App\Helpers\ModuleAccess;
use App\Services\Comges\ComgesCommitmentAlertPresenter;
use App\Services\Comges\ComgesCommitmentSearchService;
use App\Services\Indicators\IndicatorAmbitoAssignmentService;
use App\Support\Comges\ComgesBrowseContext;
use App\Support\Comges\FilamentComgesBrowseGate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Browse COMGES hosteado en Filament (SDD-275). Reutiliza search/alert/context; UI TW (sin Compass).
 */
class FilamentBrowseCommitments extends Component
{
    public string $search = '';

    public string $ambitoFilter = '';

    private ?Collection $cachedAmbitoOptions = null;

    public function mount(): void
    {
        abort_unless(FilamentComgesBrowseGate::enabled(), 403);
        abort_unless(ModuleAccess::canAccess('comges'), 403);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'ambitoFilter']);
    }

    public function render(): View
    {
        $context = ComgesBrowseContext::fromSession($this->search, $this->ambitoFilter);
        $commitments = app(ComgesCommitmentSearchService::class)->search($context);
        $alertGroups = app(ComgesCommitmentAlertPresenter::class)->forBrowse($context, $commitments);

        $alertCount = 0;
        foreach ($alertGroups as $alerts) {
            $alertCount += count($alerts);
        }

        $verificadorPendiente = 0;
        foreach ($alertGroups as $alerts) {
            foreach ($alerts as $alert) {
                $label = strtolower($alert->title);
                if (str_contains($label, 'verificador') || str_contains($label, 'medios de verificación')) {
                    $verificadorPendiente++;
                }
            }
        }

        $ssName = Auth::user()?->cenHieDependency?->nombre;

        return view('livewire.comges.filament-browse-commitments', [
            'commitments' => $commitments,
            'useScoreScale' => $context->useScoreScale,
            'isSearching' => $context->isSearching(),
            'isFiltering' => $context->isFiltering(),
            'alertGroups' => $alertGroups,
            'yearId' => $context->yearId,
            'yearAno' => $context->yearAno,
            'indicatorAmbitoOptions' => $this->getAmbitoOptions(),
            'showsEmptyCommitments' => $context->showsEmptyCommitments(),
            'kpiCommitments' => $commitments->count(),
            'kpiAlerts' => $alertCount,
            'kpiVerificador' => $verificadorPendiente,
            'kpiCorte' => $context->comgesEtapaVigente?->label() ?? '—',
            'ssName' => $ssName,
        ]);
    }

    private function getAmbitoOptions(): Collection
    {
        return $this->cachedAmbitoOptions ??= app(IndicatorAmbitoAssignmentService::class)->activeCatalog();
    }
}
