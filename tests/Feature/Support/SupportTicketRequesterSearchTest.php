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

class SupportTicketRequesterSearchTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $controlGestionRole;

    private Role $referenteRole;

    private User $admin;

    private User $controlGestionSs1;

    private User $controlGestionSs2;

    private User $referenteSs1;

    /** @var User Usuario control-gestión SS1 con “Fulano” en el nombre */
    private User $fulanoCgSs1;

    /** @var User Usuario control-gestión SS2 con “Fulano” en el nombre */
    private User $fulanoCgSs2;

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
        $this->referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $this->attachBrowseAdminRole($this->adminRole);
        $this->attachBrowseAdminRole($this->controlGestionRole);
        $this->attachBrowseAdminRole($this->referenteRole);

        $this->admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $this->controlGestionSs1 = User::factory()->create([
            'role_id' => $this->controlGestionRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $this->controlGestionSs2 = User::factory()->create([
            'role_id' => $this->controlGestionRole->id,
            'cen_hie_dependency_id' => 2,
        ]);

        $this->referenteSs1 = User::factory()->create([
            'role_id' => $this->referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $this->fulanoCgSs1 = User::factory()->create([
            'name' => 'Fulano Gestor SS1',
            'email' => 'fulano-cg-ss1@minsal-example.test',
            'role_id' => $this->controlGestionRole->id,
            'cen_hie_dependency_id' => 1,
        ]);

        $this->fulanoCgSs2 = User::factory()->create([
            'name' => 'Fulano Gestor SS2',
            'email' => 'fulano-cg-ss2@minsal-example.test',
            'role_id' => $this->controlGestionRole->id,
            'cen_hie_dependency_id' => 2,
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

    public function test_referente_cannot_search_requesters(): void
    {
        $this->actingAs($this->referenteSs1)
            ->getJson(route('centinela.support.requesters.search', ['q' => 'Fulano']))
            ->assertForbidden();
    }

    public function test_empty_query_returns_empty_results(): void
    {
        $response = $this->actingAs($this->controlGestionSs1)
            ->getJson(route('centinela.support.requesters.search', ['q' => '']));

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
    }

    public function test_control_gestion_search_returns_only_users_from_own_servicio(): void
    {
        $response = $this->actingAs($this->controlGestionSs1)
            ->getJson(route('centinela.support.requesters.search', ['q' => 'Fulano']));

        $response->assertOk();
        $ids = collect($response->json('results'))->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($this->fulanoCgSs1->id, $ids);
        $this->assertNotContains($this->fulanoCgSs2->id, $ids);
    }

    public function test_control_gestion_search_result_text_includes_name_and_email(): void
    {
        $response = $this->actingAs($this->controlGestionSs1)
            ->getJson(route('centinela.support.requesters.search', ['q' => 'Fulano Gestor SS1']));

        $response->assertOk();
        $row = collect($response->json('results'))->first(
            fn (array $item): bool => (int) $item['id'] === $this->fulanoCgSs1->id
        );
        $this->assertNotNull($row);
        $this->assertStringContainsString('fulano-cg-ss1@minsal-example.test', (string) $row['text']);
    }

    public function test_admin_without_servicio_returns_empty_results(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('centinela.support.requesters.search', ['q' => 'Fulano']));

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
    }

    public function test_admin_with_disabled_servicio_returns_empty_results(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('centinela.support.requesters.search', [
                'q' => 'Fulano',
                'ss_id' => 2,
            ]));

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
    }

    public function test_admin_search_filters_by_selected_enabled_servicio(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('centinela.support.requesters.search', [
                'q' => 'Fulano',
                'ss_id' => 1,
            ]));

        $response->assertOk();
        $ids = collect($response->json('results'))->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($this->fulanoCgSs1->id, $ids);
        $this->assertNotContains($this->fulanoCgSs2->id, $ids);
    }

    public function test_admin_search_matches_email(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('centinela.support.requesters.search', [
                'q' => 'fulano-cg-ss1',
                'ss_id' => 1,
            ]));

        $response->assertOk();
        $ids = collect($response->json('results'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($this->fulanoCgSs1->id, $ids);
    }
}
