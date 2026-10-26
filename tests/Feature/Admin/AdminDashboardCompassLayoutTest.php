<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CenHieDependency;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use TCG\Voyager\Models\Permission;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class AdminDashboardCompassLayoutTest extends TestCase
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

    private function adminUser(): User
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null]
        );
        $adminRole->permissions()->syncWithoutDetaching([$perm->id]);

        $user = User::unguarded(fn () => User::create([
            'name' => 'Admin Compass',
            'email' => 'admin-compass@test.local',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($adminRole)->save();

        return $user;
    }

    public function test_admin_dashboard_includes_compass_wrapper_for_deploy(): void
    {
        config(['centinela.usage_tracking.enabled' => false]);

        $this->actingAs($this->adminUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertSee('admin-dashboard-compass--deploy', false)
            ->assertSee('cen-compass-ui', false)
            ->assertSee('voyager-list', false);
    }

    public function test_admin_dashboard_includes_compass_wrapper_for_usage_when_enabled(): void
    {
        config(['centinela.usage_tracking.enabled' => true]);

        $this->actingAs($this->adminUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertSee('admin-dashboard-compass--usage', false)
            ->assertSee('cen-dashboard-block', false);
    }

    public function test_navbar_links_admin_to_filament_configuration_panel(): void
    {
        config([
            'centinela.usage_tracking.enabled' => false,
            'centinela.filament.enabled' => true,
        ]);

        $this->actingAs($this->adminUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertSee('cen-filament-configuration-link', false)
            ->assertSee(route('filament.configuracion.pages.centinela-dashboard'), false);
    }

    public function test_navbar_hides_filament_link_for_non_admin(): void
    {
        config([
            'centinela.usage_tracking.enabled' => false,
            'centinela.filament.enabled' => true,
        ]);

        $this->actingAs($this->controlGestionUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('cen-filament-configuration-link', false);
    }

    public function test_navbar_hides_filament_link_when_chasis_is_off(): void
    {
        config([
            'centinela.usage_tracking.enabled' => false,
            'centinela.filament.enabled' => false,
        ]);

        $this->actingAs($this->adminUser())
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('cen-filament-configuration-link', false);
    }

    private function controlGestionUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $permission = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null]
        );
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $user = User::unguarded(fn () => User::create([
            'name' => 'CG Navbar',
            'email' => 'cg-navbar@test.local',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($role)->save();

        return $user;
    }
}
