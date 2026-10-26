<?php

namespace Tests\Feature\RouteAudit;

use App\Support\Configuration\ConfigurationService;
use App\Support\Configuration\PublicDashboardGate;
use App\Support\Configuration\PublicSsDashboardGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalPublicRoutesSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function root_redirects_to_login_when_public_dashboard_off(): void
    {
        // SDD-282: kill-switch Off por defecto → `/` a login (no landing abierta).
        $this->setPublicDashboard(dash: false, ss: false);

        $this->get('/')->assertRedirect(route('voyager.login'));
    }

    /** @test */
    public function root_serves_public_landing_when_public_dashboard_on(): void
    {
        $this->setPublicDashboard(dash: true, ss: false);

        $this->get('/')->assertOk();
    }

    /** @test */
    public function legal_pages_respond_ok(): void
    {
        $this->get(route('centinela.legal.terminos'))->assertOk();
        $this->get(route('centinela.legal.privacidad'))->assertOk();
    }

    /** @test */
    public function api_health_responds_ok(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonStructure(['status']);
    }

    private function setPublicDashboard(bool $dash, bool $ss): void
    {
        $config = app(ConfigurationService::class);
        $config->setRadioOn(PublicDashboardGate::SETTING_KEY, $dash);
        $config->setRadioOn(PublicSsDashboardGate::SETTING_KEY, $ss);
        $config->invalidate();
    }
}
