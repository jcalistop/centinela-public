<?php

namespace App\Livewire\Dashboard;

use App\Models\User;
use App\Services\Dashboard\NonAdminDashboardService;
use App\Support\Dashboard\NonAdminDashboardPreview;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class NonAdminDashboardDetail extends Component
{
    public string $section = 'ambitos';

    public ?string $slug = null;

    public string $title = '';

    public bool $previewMode = false;

    public ?int $previewSsId = null;

    /**
     * @var list<array{label: string, percent: float|null, establishment: string|null, url: string|null, source: string}>
     */
    public array $rows = [];

    public function mount(string $section, ?string $slug = null): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(403);
        }

        $previewSsId = NonAdminDashboardPreview::fromRequest($user);
        $this->previewMode = $previewSsId !== null;
        $this->previewSsId = $previewSsId;

        if ($previewSsId === null) {
            if ($user->hasRole('admin') || ! (bool) config('centinela.dashboard_non_admin.enabled', true)) {
                abort(403);
            }
        }

        $this->section = $section;
        $this->slug = $slug;

        if ($section === 'instrumentos') {
            $moduleUrl = app(NonAdminDashboardService::class)->instrumentModuleUrl($user, $slug);
            if ($moduleUrl !== null) {
                $this->redirect($moduleUrl);

                return;
            }

            abort(404);
        }

        if ($section === 'establecimientos' && ($slug === null || ! ctype_digit((string) $slug))) {
            abort(404);
        }

        $this->title = $this->resolveTitle($section, $slug);
        $this->rows = app(NonAdminDashboardService::class)->detailRows($user, $section, $slug, $previewSsId);
    }

    public function render(): View
    {
        /** @var View $view */
        $view = view('livewire.dashboard.non-admin-dashboard-detail');

        return $view->extends('voyager::master')->section('content');
    }

    private function resolveTitle(string $section, ?string $slug): string
    {
        return match ($section) {
            'ambitos' => 'Indicadores por ámbito'.($slug ? ': '.$slug : ''),
            'instrumentos' => 'Indicadores por instrumento'.($slug ? ': '.$slug : ''),
            'establecimientos' => 'Indicadores — '.app(NonAdminDashboardService::class)->establishmentDetailTitle((int) $slug),
            'alertas' => 'Alertas',
            default => 'Dashboard',
        };
    }
}
