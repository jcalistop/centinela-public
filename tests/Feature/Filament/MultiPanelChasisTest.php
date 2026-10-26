<?php

namespace Tests\Feature\Filament;

use App\Models\Role;
use App\Models\User;
use App\Support\Filament\FilamentPanelRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiPanelChasisTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $parametrizador;

    private User $controlGestion;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->admin->role()->associate($adminRole)->save();

        $this->parametrizador = User::factory()->create(['role_id' => $paramRole->id]);
        $this->parametrizador->role()->associate($paramRole)->save();

        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id]);
        $this->controlGestion->role()->associate($cgRole)->save();
    }

    public function test_master_off_returns_not_found(): void
    {
        config(['centinela.filament.enabled' => false]);

        $this->actingAs($this->admin)
            ->get('/servicio-salud')
            ->assertNotFound();
    }

    public function test_legacy_filament_path_redirects_to_role_home_when_master_on(): void
    {
        config(['centinela.filament.enabled' => true]);

        $this->actingAs($this->controlGestion)
            ->get('/filament/comges/compromisos')
            ->assertRedirect(FilamentPanelRegistry::homeUrlForUser($this->controlGestion));
    }

    public function test_home_panel_ids_by_role(): void
    {
        $this->assertSame(
            FilamentPanelRegistry::CONFIGURACION,
            FilamentPanelRegistry::homePanelIdForUser($this->admin)
        );
        $this->assertSame(
            FilamentPanelRegistry::PAIS,
            FilamentPanelRegistry::homePanelIdForUser($this->parametrizador)
        );
        $this->assertSame(
            FilamentPanelRegistry::SERVICIO_SALUD,
            FilamentPanelRegistry::homePanelIdForUser($this->controlGestion)
        );
    }

    public function test_parametrizador_forbidden_on_servicio_salud_and_configuracion(): void
    {
        config(['centinela.filament.enabled' => true]);

        $this->actingAs($this->parametrizador)
            ->get('/servicio-salud')
            ->assertForbidden();

        $this->actingAs($this->parametrizador)
            ->get('/configuracion')
            ->assertForbidden();
    }

    public function test_control_gestion_can_open_servicio_salud_shell(): void
    {
        config(['centinela.filament.enabled' => true]);

        $this->actingAs($this->controlGestion)
            ->get('/servicio-salud')
            ->assertOk()
            ->assertSee('Ir a Voyager', false);
    }

    public function test_sidebar_shows_session_name_and_email_without_moving_user_menu(): void
    {
        config(['centinela.filament.enabled' => true]);

        $this->controlGestion->forceFill(['avatar' => 'users/demo.png'])->save();

        $this->actingAs($this->controlGestion)
            ->get('/servicio-salud')
            ->assertOk()
            ->assertSee('cen-sidebar-session', false)
            ->assertSee('cen-sidebar-session__dimmer', false)
            ->assertSee('cen-gradient-bg', false)
            ->assertSee('background-image', false)
            ->assertSee($this->controlGestion->name, false)
            ->assertSee($this->controlGestion->email, false)
            ->assertSee('fi-user-menu', false)
            ->assertSee('fi-user-avatar', false)
            ->assertSee('storage/users/demo.png', false);
    }

    public function test_parametrizador_can_open_pais_and_catalogo(): void
    {
        config(['centinela.filament.enabled' => true]);

        $this->actingAs($this->parametrizador)
            ->get('/pais')
            ->assertOk();

        $this->actingAs($this->parametrizador)
            ->get('/catalogo')
            ->assertOk();
    }

    public function test_public_info_panels_live_in_configuracion_admin_only(): void
    {
        config([
            'centinela.filament.enabled' => true,
            'centinela.filament.settings_enabled' => true,
        ]);

        $this->actingAs($this->admin)
            ->get('/configuracion/paneles-publicos')
            ->assertOk();

        $this->actingAs($this->parametrizador)
            ->get('/configuracion/paneles-publicos')
            ->assertForbidden();

        $this->actingAs($this->parametrizador)
            ->get('/pais/paneles-publicos')
            ->assertNotFound();
    }

    public function test_admin_user_menu_offers_return_to_voyager(): void
    {
        config(['centinela.filament.enabled' => true]);

        $this->actingAs($this->admin)
            ->get('/configuracion')
            ->assertOk()
            ->assertSee('Ir a Voyager', false)
            ->assertSee(url('/admin'), false);
    }

    public function test_non_admin_dashboard_keeps_inline_voyager_fallback(): void
    {
        config(['centinela.filament.enabled' => true]);

        $this->actingAs($this->controlGestion)
            ->get('/servicio-salud')
            ->assertOk()
            ->assertSee('Ir a Voyager', false);
    }

    public function test_admin_dashboard_does_not_repeat_voyager_link_inline(): void
    {
        config(['centinela.filament.enabled' => true]);

        $this->actingAs($this->admin)
            ->get('/configuracion')
            ->assertOk()
            ->assertDontSee('Ir a Voyager (/admin)', false);
    }

    public function test_accessible_panels_count_for_switcher_visibility(): void
    {
        $this->assertCount(4, FilamentPanelRegistry::accessiblePanelIds($this->admin));
        $this->assertCount(2, FilamentPanelRegistry::accessiblePanelIds($this->parametrizador));
        $this->assertCount(1, FilamentPanelRegistry::accessiblePanelIds($this->controlGestion));
    }
}
