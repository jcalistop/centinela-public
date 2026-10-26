<?php

namespace Tests\Feature\Dashboard;

use App\Enums\Indicators\IndicatorActivityAction;
use App\Enums\Indicators\IndicatorModuleType;
use App\Livewire\Dashboard\NonAdminDashboardPanel;
use App\Models\CenHieDependency;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\IndicatorActivity;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class NonAdminDashboardV2Test extends TestCase
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

    private function nonAdminUser(string $email = 'cg-dashboard@test.local'): User
    {
        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $this->attachBrowseAdmin($role);

        $user = User::unguarded(fn () => User::create([
            'name' => 'CG Test',
            'email' => $email,
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($role)->save();

        return $user;
    }

    public function test_non_admin_sees_dashboard_widgets_on_home(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);

        $user = $this->nonAdminUser();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertSee('Bienvenido a Centinela', false)
            ->assertSee('Indicadores por ámbito', false)
            ->assertSee('Promedio de cumplimientos', false)
            ->assertSee('Indicadores por instrumento', false)
            ->assertSee('Establecimientos', false)
            ->assertSee(
                'Promedio de cumplimiento de todos los indicadores aplicables en los diferentes instrumentos',
                false,
            )
            ->assertSee('Alertas', false)
            ->assertSee('Destacados', false);
    }

    public function test_non_admin_sees_pinned_bitacora_highlights(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);

        $user = $this->nonAdminUser('cg-destacados@test.local');

        $this->actingAs($user)
            ->withSession(['indicator_year' => $this->year]);

        $commitment = ComCommitment::unguarded(fn () => ComCommitment::create([
            'year_id' => $this->year->id,
            'clave' => 'C1',
            'nombre' => 'Compromiso 1',
            'orden' => 1,
        ]));

        $indicator = ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $commitment->id,
            'clave' => 'I-DEST',
            'nombre' => 'Indicador destacado',
            'ponderacion_indicador' => 100,
        ]));

        IndicatorActivity::unguarded(fn () => IndicatorActivity::create([
            'indicator_type' => IndicatorModuleType::Comges,
            'indicator_id' => $indicator->id,
            'action' => IndicatorActivityAction::ManualNote,
            'description' => 'Nota destacada de prueba',
            'metadata' => ['cen_hie_dependency_id' => 1],
            'is_pinned' => true,
            'user_id' => $user->id,
            'created_at' => now(),
        ]));

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Nota destacada de prueba', false)
            ->assertSee('[I-DEST] Indicador destacado', false);
    }

    public function test_rows_show_indicator_count_tags(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);

        $user = $this->nonAdminUser('cg-count-tags@test.local');

        $this->actingAs($user)
            ->withSession(['indicator_year' => $this->year]);

        $commitment = ComCommitment::unguarded(fn () => ComCommitment::create([
            'year_id' => $this->year->id,
            'clave' => 'C-COUNT',
            'nombre' => 'Compromiso conteo',
            'orden' => 1,
        ]));

        $comgesType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'voyager.compromisos.index'],
            [
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'estado' => true,
                'orden' => 1,
            ],
        ));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'comges',
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $comgesType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'habilitado' => true,
        ]));

        ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $commitment->id,
            'clave' => 'I1',
            'nombre' => 'Indicador COMGES',
            'ponderacion_indicador' => 100,
        ]));

        $this->get('/admin')
            ->assertOk()
            ->assertSee('cen-dashboard-indicator-count', false)
            ->assertSee('>1<', false);
    }

    public function test_non_admin_does_not_see_dashboard_when_disabled_by_flag(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', false);

        $user = $this->nonAdminUser('cg-dashboard-disabled@test.local');

        $this->actingAs($user)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Indicadores por ámbito');
    }

    public function test_admin_does_not_see_non_admin_dashboard_block(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->attachBrowseAdmin($role);

        $user = User::unguarded(fn () => User::create([
            'name' => 'Admin Test',
            'email' => 'admin-dashboard@test.local',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($role)->save();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Indicadores por ámbito');
    }

    public function test_admin_sees_operativo_preview_when_module_disabled(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', false);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->attachBrowseAdmin($role);

        $user = User::unguarded(fn () => User::create([
            'name' => 'Admin Preview',
            'email' => 'admin-preview@test.local',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($role)->save();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin?tab=operativo&ss=1')
            ->assertOk()
            ->assertSee('Vista operativa', false)
            ->assertSee('Vista previa (solo administrador)', false)
            ->assertSee('módulo está', false)
            ->assertSee('Indicadores por ámbito', false);
    }

    public function test_admin_preview_detail_route_with_query_params(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', false);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->attachBrowseAdmin($role);

        $user = User::unguarded(fn () => User::create([
            'name' => 'Admin Detail Preview',
            'email' => 'admin-detail-preview@test.local',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($role)->save();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('centinela.dashboard.detail', ['section' => 'ambitos', 'slug' => 'sin-ambito']).'?preview=1&ss=1')
            ->assertOk()
            ->assertSee('Indicadores por ámbito', false)
            ->assertSee('Volver a la vista operativa', false)
            ->assertSee('btn-success', false)
            ->assertSee('Preview admin', false);
    }

    public function test_detail_route_renders_for_non_admin(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);

        $user = $this->nonAdminUser('cg-detail@test.local');

        $this->actingAs($user)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('centinela.dashboard.detail', ['section' => 'ambitos', 'slug' => 'sin-ambito']))
            ->assertOk()
            ->assertSee('Indicadores por ámbito', false)
            ->assertSee('Volver al inicio', false)
            ->assertSee('btn-success', false);
    }

    public function test_instrument_detail_route_redirects_to_module(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);

        $user = $this->nonAdminUser('cg-detail-instrument@test.local');

        IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'voyager.compromisos.index'],
            [
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'estado' => true,
                'orden' => 1,
            ],
        ));

        $this->actingAs($user)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('centinela.dashboard.detail', ['section' => 'instrumentos', 'slug' => 'comges']))
            ->assertRedirect(route('voyager.compromisos.index'));
    }

    public function test_detail_route_without_slug_returns_not_found_for_establecimientos(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);

        $user = $this->nonAdminUser('cg-detail-est@test.local');

        $this->actingAs($user)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('centinela.dashboard.detail', ['section' => 'establecimientos']))
            ->assertNotFound();
    }

    public function test_livewire_panel_mounts_payload_for_non_admin(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);

        $user = $this->nonAdminUser('cg-livewire@test.local');

        $this->actingAs($user)
            ->withSession(['indicator_year' => $this->year]);

        Livewire::test(NonAdminDashboardPanel::class)
            ->assertSet('dashboard.context.year_ano', $this->year->ano)
            ->assertSet('dashboard.context.ss_nombre', 'Servicio de Salud Test');
    }
}
