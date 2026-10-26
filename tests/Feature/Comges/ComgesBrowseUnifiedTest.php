<?php

namespace Tests\Feature\Comges;

use App\Http\Controllers\Voyager\ComCommitmentController;
use App\Livewire\SearchCommitmentComponent;
use App\Models\ComCommitment;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class ComgesBrowseUnifiedTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerStubRoutes();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $this->seedDependency(),
        ]);
        $this->admin->role()->associate($adminRole)->save();
        $this->grantBrowseAdminPermissionToRole($adminRole);
        $this->seedCompromisosBread($adminRole);
        $this->registerVoyagerBreadPolicyForModel(ComCommitment::class);

        IndicatorType::unguarded(fn () => IndicatorType::updateOrCreate(
            ['id' => 1],
            [
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'route' => 'voyager.compromisos.index',
                'estado' => true,
                'orden' => 1,
            ]
        ));
    }

    public function test_unified_browse_renders_livewire_for_legacy_year(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedInstrumentSetting((int) $year->id);

        $response = $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/compromisos');

        $response->assertOk();
        $response->assertSeeLivewire(SearchCommitmentComponent::class);
        $response->assertSee('Compromisos', false);
        $response->assertSee('Resultados globales', false);
        $response->assertSee('Ingresa un texto para buscar...', false);
    }

    public function test_unified_browse_renders_score_scale_ui_for_year_2026(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedInstrumentSetting((int) $year->id);

        $response = $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/compromisos');

        $response->assertOk();
        $response->assertSeeLivewire(SearchCommitmentComponent::class);
        $response->assertSee('Buscar por clave o nombre de compromiso o indicador...', false);
        $response->assertDontSee('Ingresa un texto para buscar...', false);
    }

    public function test_actions_button_is_hidden_for_referente_parametrizador_and_visualizador(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedInstrumentSetting((int) $year->id);

        foreach (['referente', 'parametrizador', 'visualizador'] as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
            $user = User::factory()->create([
                'role_id' => $role->id,
                'cen_hie_dependency_id' => $this->admin->cen_hie_dependency_id,
            ]);
            $user->role()->associate($role)->save();
            $this->grantBrowseAdminPermissionToRole($role);
            $this->seedCompromisosBread($role);

            $this->actingAs($user)
                ->withSession(['indicator_year' => $year])
                ->get('/admin/compromisos')
                ->assertOk()
                ->assertDontSee('data-target="#ComgesActionsModal"', false)
                ->assertDontSee('>Acciones<', false);
        }
    }

    public function test_actions_button_is_visible_for_control_gestion(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedInstrumentSetting((int) $year->id);

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $this->admin->cen_hie_dependency_id,
        ]);
        $user->role()->associate($role)->save();
        $this->grantBrowseAdminPermissionToRole($role);
        $this->seedCompromisosBread($role);

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/compromisos')
            ->assertOk()
            ->assertSee('data-target="#ComgesActionsModal"', false)
            ->assertSee('Acciones', false);
    }

    private function seedInstrumentSetting(int $yearId): void
    {
        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::updateOrCreate(
            [
                'indicator_type_id' => 1,
                'year_id' => $yearId,
                'cen_hie_dependency_id' => $this->admin->cen_hie_dependency_id,
            ],
            ['habilitado' => true]
        ));
    }

    private function seedDependency(): int
    {
        return (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-BROWSE-UNI',
            'nombre' => 'Servicio browse unificado',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedCompromisosBread(Role $role): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        $dataType = DataType::unguarded(function (): DataType {
            return DataType::query()->updateOrCreate(
                ['slug' => 'compromisos'],
                [
                    'name' => 'com_commitments',
                    'display_name_singular' => 'Compromiso',
                    'display_name_plural' => 'Compromisos',
                    'model_name' => ComCommitment::class,
                    'controller' => ComCommitmentController::class,
                    'generate_permissions' => 0,
                    'server_side' => 0,
                    'details' => json_encode([
                        'order_column' => 'orden',
                        'order_display_column' => 'clave',
                        'order_direction' => 'asc',
                    ]),
                ]
            );
        });

        $permissionId = Permission::firstOrCreate(
            ['key' => 'browse_'.$dataType->name],
            ['table_name' => $dataType->name]
        )->id;
        $role->permissions()->syncWithoutDetaching([$permissionId]);
    }

    private function registerStubRoutes(): void
    {
        Route::get('/admin/indicadores-comges/{id}', fn () => '')->name('voyager.indicadores-comges.show');
        Route::get('/admin/indicadores-comges/create', fn () => '')->name('voyager.indicadores-comges.create');
        Route::get('/admin/compromisos/{id}/edit', fn () => '')->name('voyager.compromisos.edit');
    }
}
