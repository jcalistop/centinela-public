<?php

namespace App\Livewire\Configuracion\PublicInfoPanels;

use App\Livewire\Configuracion\PublicInfoPanels\Concerns\InteractsWithPublicInfoPanelModals;
use App\Models\PublicInfoPanel;
use App\Support\Dashboard\PublicInfoPanelInventory;
use App\Support\Dashboard\PublicInfoPanelPublishGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Listado de curaduría de paneles públicos en Voyager (SDD-306). Paridad con PublicInfoPanelsTable (Filament).
 */
class PublicInfoPanelIndex extends Component
{
    use InteractsWithPublicInfoPanelModals;

    public string $search = '';

    /** @var array<int, int|string> */
    public array $selected = [];

    public bool $showInventory = false;

    public bool $showDeleteConfirm = false;

    public function mount(): void
    {
        $this->requireAdmin();
    }

    public function togglePublicado(int $id): void
    {
        $this->requireAdmin();

        $panel = PublicInfoPanel::query()->findOrFail($id);
        $publicar = ! $panel->publicado;

        if ($publicar) {
            try {
                PublicInfoPanelPublishGuard::assertPublishableRecord($panel);
            } catch (ValidationException $exception) {
                $message = (string) (collect($exception->errors())->flatten()->first() ?: 'Requisitos de publicación incompletos.');
                $this->dispatch('toast', type: 'danger', message: 'No se pudo publicar el panel: '.$message);

                return;
            }
        }

        $panel->forceFill(['publicado' => $publicar])->save();

        $this->dispatch('toast', type: 'success', message: $publicar ? 'Panel publicado.' : 'Panel despublicado.');
    }

    public function moveUp(int $id): void
    {
        $this->requireAdmin();
        $this->move($id, -1);
    }

    public function moveDown(int $id): void
    {
        $this->requireAdmin();
        $this->move($id, 1);
    }

    public function confirmDeleteSelected(): void
    {
        $this->requireAdmin();

        if ($this->selectedIds() === []) {
            return;
        }

        $this->showDeleteConfirm = true;
    }

    public function cancelDeleteSelected(): void
    {
        $this->requireAdmin();

        $this->showDeleteConfirm = false;
    }

    public function deleteSelected(): void
    {
        $this->requireAdmin();

        $ids = $this->selectedIds();
        $this->showDeleteConfirm = false;

        if ($ids === []) {
            return;
        }

        $deleted = PublicInfoPanel::destroy($ids);
        $this->selected = [];

        $this->dispatch('toast', type: 'success', message: $deleted === 1 ? 'Panel eliminado.' : "{$deleted} paneles eliminados.");
    }

    public function openInventory(): void
    {
        $this->requireAdmin();

        $this->showInventory = true;
    }

    public function closeInventory(): void
    {
        $this->requireAdmin();

        $this->showInventory = false;
    }

    public function render(): View
    {
        $this->requireAdmin();

        $term = trim($this->search);
        $panels = PublicInfoPanel::query()
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('titulo', 'like', $like)->orWhere('slug', 'like', $like);
                });
            })
            ->orderBy('orden')
            ->orderBy('titulo')
            ->get();

        $inventory = null;
        if ($this->showInventory) {
            $all = PublicInfoPanelInventory::panels();
            $inventory = [
                'markdown' => PublicInfoPanelInventory::markdown($all),
                'json' => PublicInfoPanelInventory::toJson($all),
                'total' => $all->count(),
            ];
        }

        /** @var View|\Illuminate\View\View $view */
        $view = view('livewire.configuracion.public-info-panels.index', [
            'panels' => $panels,
            'inventory' => $inventory,
            'sharePanel' => $this->sharePanel(),
        ]);

        return $view->extends('voyager::master')->section('content');
    }

    /**
     * Intercambia la posición con el vecino inmediato (criterio de sort: orden, titulo) y renumera solo lo que cambia.
     */
    private function move(int $id, int $delta): void
    {
        $ids = PublicInfoPanel::query()->orderBy('orden')->orderBy('titulo')->pluck('id')->map(fn ($v): int => (int) $v)->all();
        $index = array_search($id, $ids, true);
        $target = $index === false ? false : $index + $delta;

        if ($index === false || $target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        $current = PublicInfoPanel::query()->whereIn('id', $ids)->pluck('orden', 'id');

        DB::transaction(function () use ($ids, $current): void {
            foreach ($ids as $position => $panelId) {
                if ((int) ($current[$panelId] ?? -1) === $position) {
                    continue;
                }

                PublicInfoPanel::query()->findOrFail($panelId)->forceFill(['orden' => $position])->save();
            }
        });
    }

    /**
     * @return array<int, int>
     */
    private function selectedIds(): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $this->selected),
            fn (int $id): bool => $id > 0,
        )));
    }
}
