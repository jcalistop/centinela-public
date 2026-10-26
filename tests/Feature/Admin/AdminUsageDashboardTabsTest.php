<?php

namespace Tests\Feature\Admin;

use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class AdminUsageDashboardTabsTest extends TestCase
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

    private function attachBrowseAdmin(Role $role): void
    {
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null]
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    private function adminUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->attachBrowseAdmin($role);

        $user = User::unguarded(fn () => User::create([
            'name' => 'Admin Tabs',
            'email' => 'admin-usage-tabs@test.local',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($role)->save();

        return $user;
    }

    /** @test */
    public function admin_usage_tabs_include_operativo_preview(): void
    {
        $this->actingAs($this->adminUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertSee('?tab=operativo', false)
            ->assertSee('Vista operativa', false);
    }

    /** @test */
    public function admin_sees_usage_panel_with_reload_tab_links(): void
    {
        config()->set('centinela.usage_tracking.enabled', true);

        $this->actingAs($this->adminUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertSee('Dashboard para administradores', false)
            ->assertSee('?tab=uso', false)
            ->assertSee('?tab=tablas', false)
            ->assertSee('?tab=salud', false)
            ->assertSee('chart-usage-30d', false)
            ->assertSee('apexcharts', false);
    }

    /** @test */
    public function admin_tablas_tab_does_not_include_apexcharts_script(): void
    {
        config()->set('centinela.usage_tracking.enabled', true);

        $this->actingAs($this->adminUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin?tab=tablas')
            ->assertOk()
            ->assertSee('Sesiones auth abiertas', false)
            ->assertSee('?tab=tablas', false)
            ->assertDontSee('apexcharts', false)
            ->assertDontSee('chart-usage-30d', false);
    }

    /** @test */
    public function admin_salud_tab_shows_health_section(): void
    {
        $this->actingAs($this->adminUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin?tab=salud')
            ->assertOk()
            ->assertSee('Salud del sistema', false)
            ->assertSee('Queue driver', false)
            ->assertSee('Min desde heartbeat scheduler', false)
            ->assertSee('Jobs pendientes (cola)', false)
            ->assertSee('Retención / limpiezas', false)
            ->assertSee('Failed jobs totales', false)
            ->assertDontSee('apexcharts', false);
    }

    /** @test */
    public function invalid_tab_falls_back_to_uso_view(): void
    {
        config()->set('centinela.usage_tracking.enabled', true);

        $this->actingAs($this->adminUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin?tab=invalid')
            ->assertOk()
            ->assertSee('chart-usage-30d', false)
            ->assertDontSee('Sesiones auth abiertas', false);
    }

    /** @test */
    public function non_admin_does_not_see_admin_usage_panel(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);
        config()->set('centinela.usage_tracking.enabled', true);

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $this->attachBrowseAdmin($role);

        $user = User::unguarded(fn () => User::create([
            'name' => 'CG Tabs',
            'email' => 'cg-usage-tabs@test.local',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($role)->save();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Dashboard para administradores', false);
    }

    /** @test */
    public function admin_tablas_shows_tracking_disabled_message_when_usage_off(): void
    {
        config()->set('centinela.usage_tracking.enabled', false);

        $this->actingAs($this->adminUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin?tab=tablas')
            ->assertOk()
            ->assertSee('CENTINELA_USAGE_TRACKING_ENABLED=true', false);
    }

    /** @test */
    public function admin_tablas_tab_shows_recent_users_section(): void
    {
        config()->set('centinela.usage_tracking.enabled', true);

        $this->actingAs($this->adminUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin?tab=tablas')
            ->assertOk()
            ->assertSee('Últimos usuarios activos (7 días)', false)
            ->assertSee('Top 10 Servicios de Salud', false);
    }

    /** @test */
    public function admin_dashboard_defers_deploy_panel_on_first_paint(): void
    {
        $this->actingAs($this->adminUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertSee('Cargando panel de deploy', false);
    }
}
