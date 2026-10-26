<?php

namespace App\Livewire;

use App\Enums\Indicators\IndicatorActivityAction;
use App\Enums\Indicators\IndicatorModuleType;
use App\Models\ComIndicator;
use App\Models\IndicatorActivity;
use App\Models\MetIndicator;
use App\Models\User;
use App\Services\Indicators\ComIndicatorManualNoteGuard;
use App\Services\Indicators\IndicatorActivityLogger;
use App\Services\Indicators\IndicatorNoteNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class IndicatorTimelineComponent extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'voyager-bootstrap';

    public string $indicatorType = '';

    public int $indicatorId = 0;

    #[Validate('required|string|min:3|max:2000')]
    public string $note = '';

    public bool $notifyReferentes = false;

    public bool $mantenerDestacada = false;

    public function mount(string $indicatorType, int $indicatorId): void
    {
        $this->indicatorType = $indicatorType;
        $this->indicatorId = $indicatorId;
        $this->authorizeView();
    }

    public function addNote(
        IndicatorActivityLogger $logger,
        IndicatorNoteNotifier $notifier,
        ComIndicatorManualNoteGuard $comgesGuard,
    ): void {
        $this->authorizeView();
        $this->validate();

        $user = $this->authenticatedUser();
        $this->authorize('create', IndicatorActivity::class);

        if ($this->moduleType() === IndicatorModuleType::Comges) {
            $indicator = ComIndicator::query()->findOrFail($this->indicatorId);
            if (! $comgesGuard->canAddNote($indicator, $user)) {
                $this->addError('note', 'No se pueden agregar notas en un corte cerrado.');

                return;
            }
        }

        $activity = $logger->log(
            $this->moduleType(),
            $this->indicatorId,
            IndicatorActivityAction::ManualNote,
            trim($this->note),
            ['cen_hie_dependency_id' => $user->cen_hie_dependency_id],
            $user->id,
            isPinned: $this->mantenerDestacada,
        );

        if ($this->notifyReferentes) {
            $notifier->notifyReferentes($activity);
        }

        $this->reset('note', 'notifyReferentes', 'mantenerDestacada');
        $this->resetPage();
        session()->flash('indicator_timeline_success', 'Nota agregada a la bitácora.');
    }

    public function deleteNote(int $activityId): void
    {
        $activity = IndicatorActivity::query()->findOrFail($activityId);
        $this->authorize('delete', $activity);

        $activity->delete();
        $this->resetPage();
    }

    public function togglePinned(int $activityId): void
    {
        $this->authorizeView();
        $user = $this->authenticatedUser();

        $activity = IndicatorActivity::query()
            ->forIndicator($this->moduleType(), $this->indicatorId)
            ->visibleToUser($user)
            ->findOrFail($activityId);

        $this->authorize('update', $activity);

        $wasPinned = $activity->is_pinned;
        $activity->update(['is_pinned' => ! $wasPinned]);
        $this->resetPage();

        session()->flash(
            'indicator_timeline_success',
            $wasPinned ? 'Se quitó el destacado de la nota.' : 'Nota destacada.',
        );
    }

    public function render(): View
    {
        $user = $this->authenticatedUser();
        $activities = IndicatorActivity::query()
            ->forIndicator($this->moduleType(), $this->indicatorId)
            ->visibleToUser($user)
            ->with('user:id,name')
            ->orderedForTimeline()
            ->paginate(20);

        $canAddNote = $this->canAddManualNote($user);
        $roleCanAddNote = $user->hasRole(['admin', 'control-gestion']);

        return view('livewire.indicator-timeline-component', [
            'activities' => $activities,
            'canAddNote' => $canAddNote,
            'closedCutoffMessage' => $roleCanAddNote
                && $this->moduleType() === IndicatorModuleType::Comges
                && ! $canAddNote
                ? 'No se pueden agregar notas en un corte cerrado.'
                : null,
        ]);
    }

    private function canAddManualNote(User $user): bool
    {
        if (! $user->hasRole(['admin', 'control-gestion'])) {
            return false;
        }

        if ($this->moduleType() === IndicatorModuleType::Metas) {
            return true;
        }

        $indicator = ComIndicator::query()
            ->with(['comCommitment.indicatorType.indicatorTypeSettings', 'comIndicatorCutoffs'])
            ->find($this->indicatorId);

        if ($indicator === null) {
            return false;
        }

        return app(ComIndicatorManualNoteGuard::class)->canAddNote($indicator, $user);
    }

    private function moduleType(): IndicatorModuleType
    {
        return IndicatorModuleType::from($this->indicatorType);
    }

    private function authorizeView(): void
    {
        $user = $this->authenticatedUser();

        if ($this->moduleType() === IndicatorModuleType::Metas) {
            MetIndicator::query()->findOrFail($this->indicatorId);

            return;
        }

        ComIndicator::query()->findOrFail($this->indicatorId);
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
