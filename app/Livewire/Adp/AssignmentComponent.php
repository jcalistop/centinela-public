<?php

namespace App\Livewire\Adp;

use App\Livewire\Adp\Traits\ResolvesAdpInstrument;
use App\Models\AdpPosition;
use App\Models\AdpPositionAssignment;
use App\Models\CenCenter;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class AssignmentComponent extends Component
{
    use ResolvesAdpInstrument;
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editId = null;

    public ?int $user_id = null;

    public ?int $adp_position_id = null;

    public ?int $cen_center_id = null;

    public ?string $start_date = null;

    public ?string $end_date = null;

    protected function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'adp_position_id' => 'required|exists:adp_positions,id',
            'cen_center_id' => 'required|exists:cen_centers,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }

    public function mount(?int $indicatorTypeId = null): void
    {
        $this->indicatorTypeId = $this->resolveIndicatorTypeId($indicatorTypeId);
    }

    public function render(): View
    {
        $user = $this->authUser();
        $canEdit = $user->hasRole(['admin', 'control-gestion']);

        $assignments = AdpPositionAssignment::query()
            ->with(['user', 'position', 'center'])
            ->when(! $user->hasRole('admin'), fn ($q) => $q->where('cen_hie_dependency_id', $user->cen_hie_dependency_id))
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                    ->orWhereHas('position', fn ($q) => $q->where('nombre', 'like', "%{$this->search}%"));
            }))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $users = User::query()->orderBy('name')->get();
        $positions = AdpPosition::query()->orderBy('codigo')->get();
        $centers = CenCenter::query()->orderBy('nombre_corto')->get();

        return view('livewire.adp.assignment', compact('assignments', 'canEdit', 'users', 'positions', 'centers'));
    }

    public function create(): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);

        $assignment = AdpPositionAssignment::query()->findOrFail($id);
        $this->editId = $assignment->id;
        $this->user_id = $assignment->user_id;
        $this->adp_position_id = $assignment->adp_position_id;
        $this->cen_center_id = $assignment->cen_center_id;
        $this->start_date = $assignment->start_date?->format('Y-m-d');
        $this->end_date = $assignment->end_date?->format('Y-m-d');
        $this->showForm = true;
    }

    public function save(): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);

        $this->validate();

        $data = [
            'user_id' => $this->user_id,
            'adp_position_id' => $this->adp_position_id,
            'cen_center_id' => $this->cen_center_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ];

        if ($this->editId !== null) {
            $assignment = AdpPositionAssignment::query()->findOrFail($this->editId);
            $assignment->update($data);
            $this->dispatch('toast', type: 'success', message: 'Asignación actualizada.');
        } else {
            AdpPositionAssignment::query()->create($data);
            $this->dispatch('toast', type: 'success', message: 'Asignación creada.');
        }

        $this->resetForm();
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'control-gestion']), 403);

        $assignment = AdpPositionAssignment::query()->findOrFail($id);
        $assignment->delete();

        $this->dispatch('toast', type: 'success', message: 'Asignación eliminada.');
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editId = null;
        $this->user_id = null;
        $this->adp_position_id = null;
        $this->cen_center_id = null;
        $this->start_date = null;
        $this->end_date = null;
    }
}
