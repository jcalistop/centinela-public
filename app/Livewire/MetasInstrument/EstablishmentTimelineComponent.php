<?php

namespace App\Livewire\MetasInstrument;

use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetEstablishmentResponsibleAccess;
use App\Services\Metas\MetEstablishmentTimelineService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class EstablishmentTimelineComponent extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'voyager-bootstrap';

    public int $centerId = 0;

    public int $indicatorTypeId = 0;

    public bool $showModal = false;

    public function mount(int $centerId, int $indicatorTypeId): void
    {
        $this->centerId = $centerId;
        $this->indicatorTypeId = $indicatorTypeId;
    }

    public function openModal(): void
    {
        $this->authorizeView();
        $this->resetPage('establishmentTimelinePage');
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetPage('establishmentTimelinePage');
    }

    public function render(MetEstablishmentTimelineService $timeline): View
    {
        $events = $this->resolveEventsPaginator($timeline);

        return view('livewire.metas-instrument.establishment-timeline', [
            'events' => $events,
        ]);
    }

    private function resolveEventsPaginator(MetEstablishmentTimelineService $timeline): LengthAwarePaginator
    {
        if (! $this->showModal) {
            return $this->emptyPaginator();
        }

        $year = session('indicator_year');
        if (! $year instanceof Year) {
            return $this->emptyPaginator();
        }

        $this->authorizeView();

        return $timeline->paginate(
            $this->centerId,
            (int) $year->id,
            $this->indicatorTypeId,
            (int) $this->getPage('establishmentTimelinePage'),
        );
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            MetEstablishmentTimelineService::PER_PAGE,
            1,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'establishmentTimelinePage',
            ],
        );
    }

    private function authorizeView(): void
    {
        $year = session('indicator_year');
        if (! $year instanceof Year) {
            abort(403);
        }

        $allowed = app(MetEstablishmentResponsibleAccess::class)->canViewEstablishmentIndicators(
            $this->authenticatedUser(),
            $this->centerId,
            (int) $year->id,
            $this->indicatorTypeId,
        );

        abort_unless($allowed, 403);
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
