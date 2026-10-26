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
use Livewire\Livewire;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class ComgesCommitmentBrowseSearchTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private User $admin;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerStubRoutes();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        DB::table('cen_hie_dependencies')->insert([
            'id' => 1,
            'codigo' => 'SS-BROWSE',
            'nombre' => 'Servicio browse',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->admin->role()->associate($adminRole)->save();
        $this->grantBrowseAdminPermissionToRole($adminRole);
        $this->seedCompromisosBread($adminRole);

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

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::updateOrCreate(
            [
                'indicator_type_id' => 1,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
            ],
            ['habilitado' => true]
        ));
    }

    public function test_livewire_search_filters_commitments_with_browse_context(): void
    {
        $this->seedCommitment('C-HTTP-A', 'I-A');
        $this->seedCommitment('C-HTTP-B', 'I-B');

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->assertSee('C-HTTP-A:')
            ->assertSee('C-HTTP-B:')
            ->set('search', 'HTTP-A')
            ->assertViewHas('isSearching', true)
            ->assertSee('C-HTTP-A:')
            ->assertDontSee('C-HTTP-B:');
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

    private function seedCommitment(string $commitmentClave, string $indicatorClave): void
    {
        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1]),
            'nombre_corte' => 'Corte I',
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => 1,
            'clave' => $commitmentClave,
            'nombre' => 'Compromiso '.$commitmentClave,
            'year_id' => $this->year->id,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => $indicatorClave,
            'nombre' => 'Indicador',
            'ponderacion_indicador' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $linkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_actions')->insert([
            'com_indicator_cutoff_id' => $linkId,
            'numero' => '1',
            'ponderacion_accion' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function registerStubRoutes(): void
    {
        Route::get('/admin/indicadores-comges/{id}', fn () => '')->name('voyager.indicadores-comges.show');
        Route::get('/admin/indicadores-comges/create', fn () => '')->name('voyager.indicadores-comges.create');
        Route::get('/admin/compromisos/{id}/edit', fn () => '')->name('voyager.compromisos.edit');
    }
}
