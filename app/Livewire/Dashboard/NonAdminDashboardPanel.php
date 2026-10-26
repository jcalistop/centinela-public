<?php

namespace App\Livewire\Dashboard;

use App\Models\User;
use App\Services\Dashboard\NonAdminDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NonAdminDashboardPanel extends Component
{
    public bool $previewMode = false;

    public ?int $previewSsId = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $dashboard = null;

    public function mount(NonAdminDashboardService $dashboardService): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        if ($this->previewMode) {
            if (! $user->hasRole('admin') || $this->previewSsId === null || $this->previewSsId <= 0) {
                return;
            }

            $this->dashboard = $dashboardService->forAdminPreview($user, $this->previewSsId);

            return;
        }

        if (! (bool) config('centinela.dashboard_non_admin.enabled', true)) {
            return;
        }

        if ($user->hasRole('admin')) {
            return;
        }

        $this->dashboard = $dashboardService->forUser($user);
    }

    public function render(): View
    {
        return view('livewire.dashboard.non-admin-dashboard-panel');
    }
}
