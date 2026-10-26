<?php

namespace App\Livewire\Adp\Catalog;

use App\Livewire\Adp\Traits\ResolvesAdpInstrument;
use App\Models\AdpPosition;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class PositionComponent extends Component
{
    use ResolvesAdpInstrument;
    use WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editId = null;

    public string $codigo = '';

    public string $nombre = '';

    public ?string $descripcion = null;

    protected function rules(): array
    {
        $unique = 'unique:adp_positions,codigo';
        if ($this->editId !== null) {
            $unique = 'unique:adp_positions,codigo,'.$this->editId;
        }

        return [
            'codigo' => "required|string|max:64|{$unique}",
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:10000',
        ];
    }

    public function mount(?int $indicatorTypeId = null): void
    {
        $this->indicatorTypeId = $this->resolveIndicatorTypeId($indicatorTypeId);
    }

    public function render(): View
    {
        $user = $this->authUser();
        $canEdit = $user->hasRole(['admin', 'parametrizador']);

        $positions = AdpPosition::query()
            ->when($this->search !== '', fn ($q) => $q->where(function ($q) {
                $q->where('codigo', 'like', "%{$this->search}%")
                    ->orWhere('nombre', 'like', "%{$this->search}%");
            }))
            ->orderBy('codigo')
            ->paginate(20);

        return view('livewire.adp.catalog.position', compact('positions', 'canEdit'));
    }

    public function create(): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'parametrizador']), 403);

        $this->resetForm();
        $this->showForm = true;
        $this->editId = null;
    }

    public function edit(int $id): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'parametrizador']), 403);

        $position = AdpPosition::query()->findOrFail($id);
        $this->editId = $position->id;
        $this->codigo = $position->codigo;
        $this->nombre = $position->nombre;
        $this->descripcion = $position->descripcion;
        $this->showForm = true;
    }

    public function save(): void
    {
        $user = $this->authUser();
        abort_unless($user->hasRole(['admin', 'parametrizador']), 403);

        $this->validate();

        if ($this->editId !== null) {
            $position = AdpPosition::query()->findOrFail($this->editId);
            $position->update([
                'codigo' => $this->codigo,
                'nombre' => $this->nombre,
                'descripcion' => $this->descripcion,
            ]);
            $this->dispatch('toast', type: 'success', message: 'Cargo actualizado.');
        } else {
            AdpPosition::query()->create([
                'codigo' => $this->codigo,
                'nombre' => $this->nombre,
                'descripcion' => $this->descripcion,
            ]);
            $this->dispatch('toast', type: 'success', message: 'Cargo creado.');
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
        abort_unless($user->hasRole(['admin', 'parametrizador']), 403);

        $position = AdpPosition::query()->findOrFail($id);
        $position->delete();

        $this->dispatch('toast', type: 'success', message: 'Cargo eliminado.');
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editId = null;
        $this->codigo = '';
        $this->nombre = '';
        $this->descripcion = null;
    }
}
