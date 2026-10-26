<?php

namespace App\Livewire\Configuracion\PublicInfoPanels\Concerns;

use App\Models\PublicInfoPanel;
use App\Models\User;
use App\Support\Dashboard\PublicInfoPanelAgentGuide;
use App\Support\Dashboard\PublicInfoPanelPreview;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Autorización admin y modales compartidos (Compartir, Contratos para agentes) de la curaduría de paneles (SDD-306).
 */
trait InteractsWithPublicInfoPanelModals
{
    public bool $showShare = false;

    #[Locked]
    public ?int $shareId = null;

    public bool $showAgentGuide = false;

    /**
     * Solo rol `admin` (curador). No usa FilamentSettingsGate (H-ARB-03).
     */
    protected function requireAdmin(): User
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->hasRole('admin')) {
            abort(403);
        }

        return $user;
    }

    public function openShare(int $id): void
    {
        $this->requireAdmin();

        $panel = PublicInfoPanel::query()->findOrFail($id);

        if (! $panel->hasRequiredAssets()) {
            $this->dispatch('toast', type: 'danger', message: 'El panel no tiene los archivos mínimos para compartirse.');

            return;
        }

        $this->shareId = $panel->getKey();
        $this->showShare = true;
    }

    public function closeShare(): void
    {
        $this->requireAdmin();

        $this->showShare = false;
        $this->shareId = null;
    }

    public function openAgentGuide(): void
    {
        $this->requireAdmin();

        $this->showAgentGuide = true;
    }

    public function closeAgentGuide(): void
    {
        $this->requireAdmin();

        $this->showAgentGuide = false;
    }

    public function downloadAgentGuide(): StreamedResponse
    {
        $this->requireAdmin();

        return PublicInfoPanelAgentGuide::downloadResponse();
    }

    protected function sharePanel(): ?PublicInfoPanel
    {
        if (! $this->showShare || $this->shareId === null) {
            return null;
        }

        return PublicInfoPanel::query()->find($this->shareId);
    }

    protected function previewImageUrl(?PublicInfoPanel $panel): ?string
    {
        if (! $panel instanceof PublicInfoPanel || ! filled($panel->preview_png_path)) {
            return null;
        }

        if ($panel->publicado) {
            return $panel->previewUrl();
        }

        return PublicInfoPanelPreview::signedTypedUrl($panel->slug, 'preview');
    }
}
