<?php

namespace App\Livewire\Configuracion\PublicInfoPanels;

use App\Livewire\Configuracion\PublicInfoPanels\Concerns\InteractsWithPublicInfoPanelModals;
use App\Models\PublicInfoPanel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Vista read de un panel público en Voyager (SDD-306). Paridad con PublicInfoPanelInfolist (Filament).
 */
class PublicInfoPanelShow extends Component
{
    use InteractsWithPublicInfoPanelModals;

    #[Locked]
    public int $panelId;

    public function mount(PublicInfoPanel $panel): void
    {
        $this->requireAdmin();

        $this->panelId = (int) $panel->getKey();
    }

    public function render(): View
    {
        $this->requireAdmin();

        $panel = PublicInfoPanel::query()->findOrFail($this->panelId);

        /** @var View|\Illuminate\View\View $view */
        $view = view('livewire.configuracion.public-info-panels.show', [
            'panel' => $panel,
            'thumbnailUrl' => $this->previewImageUrl($panel),
            'sharePanel' => $this->sharePanel(),
        ]);

        return $view->extends('voyager::master')->section('content');
    }
}
