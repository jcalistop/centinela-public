<?php

namespace Tests\Feature\PublicDashboard;

use App\Models\CenHieDependency;
use App\Models\PublicDashboardVisitCounter;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Support\Configuration\ConfigurationService;
use App\Support\Configuration\PublicDashboardGate;
use App\Support\Configuration\PublicSsDashboardGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class PublicDashboardVisitCounterTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create([
            'ano' => 2026,
            'default' => true,
        ]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
    }

    public function test_two_landing_visits_increment_total_to_two(): void
    {
        $this->enablePublicDashboard();

        $this->get('/')->assertOk();
        $this->get('/')->assertOk();

        $this->assertSame(2, PublicDashboardVisitCounter::currentTotal());
    }

    public function test_admin_sees_visit_total_when_usage_tracking_is_off(): void
    {
        $this->enablePublicDashboard();
        $this->setUsageTracking(false);

        $this->get('/')->assertOk();
        $this->get('/')->assertOk();

        $this->actingAs($this->userWithRole('admin', 'admin-visits@test.local'))
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin?tab=tablas')
            ->assertOk()
            ->assertSee('data-public-dashboard-visits="2"', false)
            ->assertSee('Sesiones auth abiertas', false)
            ->assertSee('CENTINELA_USAGE_TRACKING_ENABLED=true', false);
    }

    public function test_landing_off_does_not_increment_and_redirects_to_login(): void
    {
        $this->enablePublicDashboard(dash: false);

        $this->get('/')
            ->assertRedirect(route('voyager.login'));

        $this->assertSame(0, PublicDashboardVisitCounter::currentTotal());
    }

    public function test_other_public_routes_do_not_increment(): void
    {
        $this->enablePublicDashboard(ss: true);

        $this->get(route('centinela.publico.plataforma'))->assertOk();
        $this->get(route('centinela.publico.panel', ['slug' => 'inexistente']))->assertNotFound();
        $this->get(route('centinela.publico.ss', ['ss' => 999999]))->assertNotFound();

        $this->assertSame(0, PublicDashboardVisitCounter::currentTotal());
    }

    public function test_non_admin_does_not_see_visit_total(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);
        PublicDashboardVisitCounter::query()->update(['total' => 7]);

        $this->actingAs($this->userWithRole('control-gestion', 'cg-visits@test.local'))
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('data-public-dashboard-visits=', false);
    }

    private function enablePublicDashboard(bool $dash = true, bool $ss = false): void
    {
        $config = app(ConfigurationService::class);
        $config->setRadioOn(PublicDashboardGate::SETTING_KEY, $dash);
        $config->setRadioOn(PublicSsDashboardGate::SETTING_KEY, $ss);
        $config->invalidate();
    }

    private function setUsageTracking(bool $enabled): void
    {
        $config = app(ConfigurationService::class);
        $config->setRadioOn('module.usage_tracking_enabled', $enabled);
        $config->invalidate();
        $config->overrideConfig();
    }

    private function userWithRole(string $roleName, string $email): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['display_name' => $roleName]
        );
        $permission = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null]
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::unguarded(fn () => User::create([
            'name' => $roleName,
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($role)->save();

        return $user;
    }
}
