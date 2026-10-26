<?php

namespace Tests\Feature;

use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManualFeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::firstOrCreate(
            ['id' => 1],
            ['codigo' => 'SS01', 'nombre' => 'Servicio Test', 'tipo_dependencia' => 'SERVICIO']
        ));

        $this->user = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
    }

    /** @test */
    public function user_manual_responds_with_not_found_when_feature_disabled(): void
    {
        config(['centinela.user_manual.enabled' => false]);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('user-manual.index'))
            ->assertNotFound();

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $this->year])
            ->getJson(route('user-manual.context', ['path' => '/admin']))
            ->assertNotFound();
    }

    /** @test */
    public function user_manual_index_is_ok_when_feature_enabled(): void
    {
        config(['centinela.user_manual.enabled' => true]);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('user-manual.index'))
            ->assertOk();
    }

    /** @test */
    public function user_manual_context_resolves_centinela_dashboard_for_admin_home_path(): void
    {
        config(['centinela.user_manual.enabled' => true]);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $this->year])
            ->getJson(route('user-manual.context', ['path' => '/admin']))
            ->assertOk()
            ->assertJson(['id' => 'centinela-dashboard']);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $this->year])
            ->getJson(route('user-manual.context', ['path' => '/admin/']))
            ->assertOk()
            ->assertJson(['id' => 'centinela-dashboard']);
    }

    /** @test */
    public function user_manual_context_does_not_resolve_dashboard_for_nested_admin_path_without_route_name(): void
    {
        config(['centinela.user_manual.enabled' => true]);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $this->year])
            ->getJson(route('user-manual.context', ['path' => '/admin/users']))
            ->assertOk()
            ->assertJson(['id' => 'general']);
    }
}
