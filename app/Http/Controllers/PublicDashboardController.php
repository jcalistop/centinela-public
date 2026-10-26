<?php

namespace App\Http\Controllers;

use App\Models\CenHieDependency;
use App\Models\PublicDashboardVisitCounter;
use App\Models\PublicInfoPanel;
use App\Models\Year;
use App\Services\Dashboard\PublicSsDashboardService;
use App\Support\Configuration\PublicDashboardGate;
use App\Support\Configuration\PublicSsDashboardGate;
use App\Support\Dashboard\PublicInfoPanelPreview;
use App\Support\Dashboard\PublicPanelInconsistencyMailto;
use App\Support\Dashboard\PublicPanelSubmissionMailto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicDashboardController extends Controller
{
    public function __construct(
        private readonly PublicSsDashboardService $dashboard,
    ) {}

    public function landing(): View|RedirectResponse
    {
        if (! PublicDashboardGate::enabled()) {
            return $this->redirectToLogin();
        }

        $panels = PublicInfoPanel::query()
            ->published()
            ->orderBy('orden')
            ->orderBy('id')
            ->paginate(8);

        $services = PublicSsDashboardGate::enabled()
            ? $this->dashboard->landingServices()
            : [];

        PublicDashboardVisitCounter::recordVisit();

        return view('centinela.publico.landing', [
            'panels' => $panels,
            'services' => $services,
            'ssEnabled' => PublicSsDashboardGate::enabled(),
            'panelSubmissionMailto' => PublicPanelSubmissionMailto::href(),
            'panelSubmissionEmail' => PublicPanelSubmissionMailto::recipient(),
        ]);
    }

    public function plataforma(): View|RedirectResponse
    {
        if (! PublicDashboardGate::enabled()) {
            return $this->redirectToLogin();
        }

        return view('centinela.publico.plataforma');
    }

    public function showPanel(string $slug): View|RedirectResponse
    {
        if (! PublicDashboardGate::enabled()) {
            return $this->redirectToLogin();
        }

        $panel = PublicInfoPanel::query()
            ->published()
            ->where('slug', $slug)
            ->first();

        if ($panel === null) {
            abort(404);
        }

        return view('centinela.publico.panel-show', [
            'panel' => $panel,
            'isPreview' => false,
            'panelInconsistencyMailto' => PublicPanelInconsistencyMailto::href($panel),
        ]);
    }

    public function previewPanel(Request $request, string $slug): View|RedirectResponse
    {
        if (! PublicDashboardGate::enabled()) {
            return $this->redirectToLogin();
        }

        $panel = PublicInfoPanel::query()
            ->where('slug', $slug)
            ->first();

        if ($panel === null || ! $panel->hasRequiredAssets()) {
            abort(404);
        }

        $expiresAt = (int) $request->query('expires', 0);
        if ($expiresAt > 0) {
            PublicInfoPanelPreview::grant($slug, $expiresAt);
        }

        return view('centinela.publico.panel-show', [
            'panel' => $panel,
            'isPreview' => true,
            'panelInconsistencyMailto' => null,
        ]);
    }

    public function show(Request $request, int $ss): View|RedirectResponse
    {
        if (! PublicDashboardGate::enabled()) {
            return $this->redirectToLogin();
        }

        if (! PublicSsDashboardGate::enabled()) {
            abort(404);
        }

        $dependency = CenHieDependency::query()->enabled()->whereKey($ss)->first();
        if ($dependency === null) {
            abort(404);
        }

        $yearParam = $request->query('year');
        if ($yearParam === null || $yearParam === '') {
            $year = $this->dashboard->defaultYear();
            if ($year === null) {
                abort(404);
            }
        } else {
            if (! is_numeric($yearParam)) {
                abort(404);
            }
            $year = Year::query()->whereKey((int) $yearParam)->first();
            if ($year === null) {
                abort(404);
            }
        }

        $summary = $this->dashboard->summaryFor($dependency, $year);

        return view('centinela.publico.show', [
            'summary' => $summary,
        ]);
    }

    private function redirectToLogin(): RedirectResponse
    {
        return redirect()->route('voyager.login');
    }
}
