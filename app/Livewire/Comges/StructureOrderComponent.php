<?php

namespace App\Livewire\Comges;

use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\User;
use App\Models\Year;
use App\Services\Comges\ComIndicatorPlacementService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class StructureOrderComponent extends Component
{
    public function mount(): void
    {
        $this->authorizeStructureEdit();
        $this->js('window.initComgesStructureOrder && window.initComgesStructureOrder()');
    }

    /**
     * @param  array{commitments?: array<int, array{id: int|string, indicator_ids: array<int, int|string>}>}  $payload
     */
    public function applyStructure(array $payload): void
    {
        $this->authorizeStructureEdit();

        $year = session('indicator_year');
        abort_unless($year instanceof Year, 500);

        $commitments = $payload['commitments'] ?? $payload;

        app(ComIndicatorPlacementService::class)->applyStructure($commitments, $year);

        session()->flash('structure_order_message', 'Estructura COMGES actualizada.');
        $this->dispatch('structure-order-saved');
    }

    public function render(): View
    {
        return view('livewire.comges.structure-order', [
            'commitments' => $this->loadCommitments(),
        ]);
    }

    /**
     * @return Collection<int, ComCommitment>
     */
    private function loadCommitments(): Collection
    {
        $year = session('indicator_year');

        if (! $year instanceof Year) {
            return collect();
        }

        return ComCommitment::query()
            ->where('year_id', $year->id)
            ->with(['comIndicators'])
            ->orderBy('orden')
            ->orderBy('clave')
            ->get();
    }

    private function authorizeStructureEdit(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can('edit', app(ComIndicator::class)), 403);
    }
}
