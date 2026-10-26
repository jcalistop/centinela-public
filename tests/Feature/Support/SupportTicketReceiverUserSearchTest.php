<?php

namespace Tests\Feature\Support;

use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class SupportTicketReceiverUserSearchTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $controlGestionRole;

    private User $admin;

    /** @var User Usuario admin con “Fulano” en el nombre */
    private User $adminFulano;

    /** @var User Usuario control-gestión con “Fulano” en el nombre */
    private User $controlGestionFulano;

    private User $controlGestionSearcher;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('centinela.support.enabled', true);

        Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Uno',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS02',
            'nombre' => 'Servicio de Salud Dos',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => false,
        ]));

        $this->adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->controlGestionRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);

        $this->attachBrowseAdminRole($this->adminRole);
        $this->attachBrowseAdminRole($this->controlGestionRole);

        $this->admin = User::factory()->create([
            'name' => 'Admin Sitio',
            'role_id' => $this->adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $this->adminFulano = User::factory()->create([
            'name' => 'Fulano Admin SSO',
            'email' => 'fulano-admin@minsal-example.test',
            'role_id' => $this->adminRole->id,
            'cen_hie_dependency_id' => 2,
        ]);

        $this->controlGestionFulano = User::factory()->create([
            'name' => 'Fulano Gestor SS1',
            'email' => 'fulano-cg-ss1@minsal-example.test',
            'role_id' => $this->controlGestionRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $this->controlGestionSearcher = User::factory()->create([
            'name' => 'Otros Gestión',
            'email' => 'otros-cg-search@minsal-example.test',
            'role_id' => $this->controlGestionRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
    }

    private function attachBrowseAdminRole(Role $role): void
    {
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null],
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    public function test_control_gestion_cannot_search_users_for_receiver_form(): void
    {
        $this->actingAs($this->controlGestionSearcher)
            ->getJson(route('centinela.support.receivers.users.search', [
                'scope' => 'global',
                'q' => 'Fulano',
            ]))
            ->assertForbidden();
    }

    public function test_scope_global_returns_only_matching_admins(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('centinela.support.receivers.users.search', [
                'scope' => 'global',
                'q' => 'Fulano',
            ]));

        $response->assertOk();
        $ids = collect($response->json('results'))->pluck('id')->all();

        $this->assertContains((string) $this->adminFulano->id, $ids);
        $this->assertNotContains((string) $this->controlGestionFulano->id, $ids);
    }

    public function test_scope_ss_returns_only_control_gestion_of_selected_servicio_enabled(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('centinela.support.receivers.users.search', [
                'scope' => 'ss',
                'cen_hie_dependency_id' => 1,
                'q' => 'Fulano',
            ]));

        $response->assertOk();
        $ids = collect($response->json('results'))->pluck('id')->all();

        $this->assertContains((string) $this->controlGestionFulano->id, $ids);
        $this->assertNotContains((string) $this->adminFulano->id, $ids);
    }

    public function test_scope_ss_without_enabled_servicio_returns_empty_results(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('centinela.support.receivers.users.search', [
                'scope' => 'ss',
                'cen_hie_dependency_id' => 2,
                'q' => 'Fulano',
            ]));

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
        $this->assertFalse($response->json('pagination.more'));
    }

    public function test_requires_scope_parameter(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('centinela.support.receivers.users.search', ['q' => 'Fulano']))
            ->assertUnprocessable();
    }

    public function test_requires_cen_when_scope_is_ss(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('centinela.support.receivers.users.search', [
                'scope' => 'ss',
                'q' => 'Fulano',
            ]))
            ->assertUnprocessable();
    }

    public function test_short_query_returns_empty_results(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('centinela.support.receivers.users.search', [
                'scope' => 'global',
                'q' => 'F',
            ]));

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
        $this->assertFalse($response->json('pagination.more'));
    }
}
