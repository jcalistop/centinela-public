<?php

namespace App\View\Composers;

use App\Models\CenHieDependency;
use App\Models\PublicDashboardVisitCounter;
use App\Models\User;
use App\Models\Year;
use App\Support\Admin\AdminUsageDashboardService;
use App\Support\Dashboard\NonAdminDashboardPreview;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class VoyagerDashboardComposer
{
    public function __construct(
        private AdminUsageDashboardService $adminUsageDashboard,
    ) {}

    public function compose(View $view): void
    {
        $this->composeAdminUsagePanel($view);
        $view->with('showNonAdminDashboard', $this->shouldShowNonAdminDashboard());
    }

    private function shouldShowNonAdminDashboard(): bool
    {
        if (! (bool) config('centinela.dashboard_non_admin.enabled', true)) {
            return false;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return false;
        }

        $year = session('indicator_year');

        return $year !== null && $user->cen_hie_dependency_id !== null;
    }

    private function composeAdminUsagePanel(View $view): void
    {
        $user = Auth::user();
        $usageEnabled = (bool) config('centinela.usage_tracking.enabled', false);
        $canSeeUsageDashboard = $user instanceof User && $user->hasRole('admin');
        $activeUsageTab = $this->adminUsageDashboard->resolveTab(request()->query('tab'));

        $usageDashboard = null;
        $systemHealth = null;
        $retentionOps = null;

        if ($canSeeUsageDashboard) {
            if ($usageEnabled) {
                $usageDashboard = match ($activeUsageTab) {
                    'tablas' => $this->adminUsageDashboard->tablesPayload(),
                    'uso' => $this->adminUsageDashboard->chartsPayload(),
                    default => null,
                };
            }

            if ($activeUsageTab === 'salud') {
                $systemHealth = $this->adminUsageDashboard->systemHealthPayload();
                $retentionOps = $this->adminUsageDashboard->retentionOpsPayload();
            }
        }

        $view->with([
            'usageEnabled' => $usageEnabled,
            'canSeeUsageDashboard' => $canSeeUsageDashboard,
            'showAdminUsagePanel' => $canSeeUsageDashboard,
            'activeUsageTab' => $activeUsageTab,
            'usageDashboard' => $usageDashboard,
            'systemHealth' => $systemHealth,
            'retentionOps' => $retentionOps,
            'nonAdminDashboardPreviewSsId' => $this->resolveNonAdminPreviewSsId($user),
            'nonAdminDashboardPreviewServicios' => $canSeeUsageDashboard
                ? $this->previewServiciosSalud()
                : collect(),
            'nonAdminDashboardModuleEnabled' => (bool) config('centinela.dashboard_non_admin.enabled', true),
            'publicDashboardVisitTotal' => ($canSeeUsageDashboard && $activeUsageTab === 'tablas')
                ? PublicDashboardVisitCounter::currentTotal()
                : null,
        ]);
    }

    private function resolveNonAdminPreviewSsId(?User $user): ?int
    {
        if (! $user instanceof User || ! $user->hasRole('admin')) {
            return null;
        }

        if (! session('indicator_year') instanceof Year) {
            return null;
        }

        return NonAdminDashboardPreview::resolveSsId($user);
    }

    /**
     * @return Collection<int, CenHieDependency>
     */
    private function previewServiciosSalud()
    {
        return CenHieDependency::query()
            ->enabled()
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);
    }
}
