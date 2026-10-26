<?php

namespace Tests\Feature\Comges;

use App\Http\Controllers\Voyager\ComIndicatorController;
use App\Models\ComAction;
use App\Models\ComIndicator;
use App\Models\ComVerifiableDocument;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Comges\ComIndicatorDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;
use Tests\Support\CreatesMinimalDocumentsBread;
use Tests\TestCase;

class ComIndicatorDeletionTest extends TestCase
{
    use CreatesMinimalDocumentsBread;
    use RefreshDatabase;

    private Year $year;

    private User $admin;

    private User $referente;

    private int $dependencyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dependencyId = $this->seedDependency();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $this->dependencyId,
        ]);
        $this->admin->role()->associate($adminRole)->save();

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $this->referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $this->dependencyId,
        ]);
        $this->referente->role()->associate($referenteRole)->save();

        $this->grantBrowseAdminPermissionToRole($adminRole);
        $this->grantIndicadorBreadPermissions($adminRole, edit: true);

        $this->grantBrowseAdminPermissionToRole($referenteRole);
        $this->grantIndicadorBreadPermissions($referenteRole, edit: false);

        $this->seedIndicadoresBread();
        $this->seedComgesRelatedDataTypes();
        $this->registerVoyagerBreadPolicyForModel(ComIndicator::class);

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
                'cen_hie_dependency_id' => $this->dependencyId,
            ],
            ['habilitado' => true]
        ));
    }

    public function test_admin_read_shows_delete_button_and_modal(): void
    {
        $indicatorId = $this->seedBareIndicator();

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/indicadores-comges/'.$indicatorId)
            ->assertOk()
            ->assertSee('id="delete-'.$indicatorId.'"', false)
            ->assertSee('¿Está seguro?', false)
            ->assertSee('Eliminar definitivamente', false);
    }

    public function test_deletion_service_preview_counts_unique_referente_pairs(): void
    {
        $seed = $this->seedParametrizedIndicator();
        $indicator = ComIndicator::query()->findOrFail($seed['indicator_id']);

        Schema::table('com_indicator_setting_user', function ($table): void {
            $table->dropUnique('uq_com_ind_set_user');
        });

        DB::table('com_indicator_setting_user')->insert([
            ['com_indicator_setting_id' => $seed['setting_id'], 'user_id' => $this->admin->id],
            ['com_indicator_setting_id' => $seed['setting_id'], 'user_id' => $this->admin->id],
        ]);

        $preview = app(ComIndicatorDeletionService::class)->preview($indicator);
        $countsByKey = collect($preview['items'])->mapWithKeys(fn (array $item) => [$item['key'] => $item['count']]);

        $this->assertSame(3, (int) DB::table('com_indicator_setting_user')
            ->where('com_indicator_setting_id', $seed['setting_id'])
            ->count());
        $this->assertSame(1, $countsByKey['referentes']);

        DB::table('com_indicator_setting_user')
            ->where('com_indicator_setting_id', $seed['setting_id'])
            ->where('user_id', $this->admin->id)
            ->delete();
        DB::table('com_indicator_setting_user')->insert([
            'com_indicator_setting_id' => $seed['setting_id'],
            'user_id' => $this->admin->id,
        ]);

        Schema::table('com_indicator_setting_user', function ($table): void {
            $table->unique(['com_indicator_setting_id', 'user_id'], 'uq_com_ind_set_user');
        });
    }

    public function test_deletion_service_preview_returns_expected_counts(): void
    {
        $seed = $this->seedParametrizedIndicator();
        $indicator = ComIndicator::query()->findOrFail($seed['indicator_id']);

        $preview = app(ComIndicatorDeletionService::class)->preview($indicator);

        $countsByKey = collect($preview['items'])->mapWithKeys(fn (array $item) => [$item['key'] => $item['count']]);

        $this->assertSame(1, $countsByKey['cortes']);
        $this->assertSame(1, $countsByKey['acciones']);
        $this->assertSame(1, $countsByKey['evaluaciones']);
        $this->assertSame(1, $countsByKey['medios_verificacion']);
        $this->assertSame(1, $countsByKey['sensibilidad']);
        $this->assertSame(1, $countsByKey['sensibilidad_deps']);
        $this->assertSame(1, $countsByKey['referentes']);
        $this->assertSame(1, $countsByKey['visualizaciones']);
        $this->assertSame(1, $countsByKey['ambitos']);
        $this->assertSame(1, $countsByKey['bitacora']);
        $this->assertGreaterThan(1, $preview['total']);
    }

    public function test_deletion_service_preview_for_bare_indicator_has_zero_satellites(): void
    {
        $indicatorId = $this->seedBareIndicator();
        $indicator = ComIndicator::query()->findOrFail($indicatorId);

        $preview = app(ComIndicatorDeletionService::class)->preview($indicator);
        $countsByKey = collect($preview['items'])->mapWithKeys(fn (array $item) => [$item['key'] => $item['count']]);

        $this->assertSame(0, $countsByKey['cortes']);
        $this->assertSame(0, $countsByKey['acciones']);
        $this->assertSame(0, $countsByKey['evaluaciones']);
        $this->assertSame(0, $countsByKey['medios_verificacion']);
        $this->assertSame(0, $countsByKey['sensibilidad']);
        $this->assertSame(0, $countsByKey['sensibilidad_deps']);
        $this->assertSame(0, $countsByKey['referentes']);
        $this->assertSame(0, $countsByKey['visualizaciones']);
        $this->assertSame(0, $countsByKey['settings']);
        $this->assertSame(0, $countsByKey['ambitos']);
        $this->assertSame(0, $countsByKey['bitacora']);
        $this->assertSame(1, $countsByKey['indicador']);
        $this->assertSame(1, $preview['total']);
    }

    public function test_build_context_does_not_use_db_table(): void
    {
        $method = new \ReflectionMethod(ComIndicatorDeletionService::class, 'buildContext');
        $filename = $method->getFileName();
        $this->assertNotFalse($filename);

        $start = $method->getStartLine();
        $end = $method->getEndLine();
        $lines = array_slice(file($filename), $start - 1, $end - $start + 1);
        $body = implode('', $lines);

        $this->assertStringNotContainsString('DB::table', $body);
    }

    public function test_admin_can_delete_parametrized_indicator_cascade(): void
    {
        $seed = $this->seedParametrizedIndicator();

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->delete('/admin/indicadores-comges/'.$seed['indicator_id'])
            ->assertRedirect(route('voyager.compromisos.index'))
            ->assertSessionHas('message', 'Indicador eliminado correctamente.')
            ->assertSessionHas('alert-type', 'success');

        $this->assertDatabaseMissing('com_indicators', ['id' => $seed['indicator_id']]);
        $this->assertDatabaseMissing('com_indicator_cutoffs', ['id' => $seed['cutoff_link_id']]);
        $this->assertDatabaseMissing('com_actions', ['id' => $seed['action_id']]);
        $this->assertDatabaseMissing('com_evaluations', ['com_action_id' => $seed['action_id']]);
        $this->assertDatabaseMissing('com_verifiable_documents', ['com_action_id' => $seed['action_id']]);
        $this->assertDatabaseMissing('com_sensitivity_tables', ['id' => $seed['sensitivity_table_id']]);
        $this->assertDatabaseMissing('com_sensitivity_dependencies', ['com_action_id' => $seed['action_id']]);
        $this->assertDatabaseMissing('com_visualizations', ['com_indicator_cutoff_id' => $seed['cutoff_link_id']]);
        $this->assertDatabaseMissing('com_indicator_settings', ['id' => $seed['setting_id']]);
        $this->assertDatabaseMissing('com_indicator_setting_user', ['com_indicator_setting_id' => $seed['setting_id']]);
        $this->assertDatabaseMissing('indicator_ambito_assignments', ['id' => $seed['ambito_assignment_id']]);
        $this->assertDatabaseMissing('indicator_activities', ['indicator_id' => $seed['indicator_id'], 'indicator_type' => 'comges']);
    }

    public function test_admin_delete_removes_satellites_of_all_ss(): void
    {
        $seed = $this->seedParametrizedIndicator();
        $ss2 = $this->seedSecondDependencySatellites($seed);
        $settingSs2Id = (int) DB::table('com_indicator_settings')
            ->where('com_indicator_id', $seed['indicator_id'])
            ->where('cen_hie_dependency_id', $ss2)
            ->value('id');

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->delete('/admin/indicadores-comges/'.$seed['indicator_id'])
            ->assertRedirect(route('voyager.compromisos.index'));

        $this->assertDatabaseMissing('com_indicator_settings', ['cen_hie_dependency_id' => $ss2]);
        $this->assertDatabaseMissing('com_indicator_setting_user', ['com_indicator_setting_id' => $settingSs2Id]);
        $this->assertDatabaseMissing('com_evaluations', ['cen_hie_dependency_id' => $ss2]);
        $this->assertDatabaseMissing('com_verifiable_documents', ['cen_hie_dependency_id' => $ss2]);
        $this->assertDatabaseMissing('com_sensitivity_dependencies', ['cen_hie_dependency_id' => $ss2]);
    }

    public function test_preview_counts_satellites_of_all_ss(): void
    {
        $seed = $this->seedParametrizedIndicator();
        $this->seedSecondDependencySatellites($seed);

        $this->actingAs($this->admin);

        $items = collect(app(ComIndicatorDeletionService::class)->preview(
            ComIndicator::findOrFail($seed['indicator_id'])
        )['items'])->pluck('count', 'key');

        $this->assertSame(2, $items['settings']);
        $this->assertSame(2, $items['referentes']);
        $this->assertSame(2, $items['evaluaciones']);
        $this->assertSame(2, $items['medios_verificacion']);
        $this->assertSame(2, $items['sensibilidad_deps']);
    }

    public function test_user_without_delete_permission_cannot_see_delete_button(): void
    {
        $indicatorId = $this->seedBareIndicator();

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/indicadores-comges/'.$indicatorId)
            ->assertOk()
            ->assertDontSee('id="delete-'.$indicatorId.'"', false)
            ->assertDontSee('Eliminar definitivamente', false);
    }

    public function test_user_without_delete_permission_gets_forbidden_on_delete(): void
    {
        $seed = $this->seedParametrizedIndicator();

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->delete('/admin/indicadores-comges/'.$seed['indicator_id'])
            ->assertForbidden();

        $this->assertDatabaseHas('com_indicators', ['id' => $seed['indicator_id']]);
    }

    public function test_delete_indicator_from_other_year_returns_not_found(): void
    {
        $otherYear = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));
        $seed = $this->seedParametrizedIndicator(yearId: (int) $otherYear->id);

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->delete('/admin/indicadores-comges/'.$seed['indicator_id'])
            ->assertNotFound();

        $this->assertDatabaseHas('com_indicators', ['id' => $seed['indicator_id']]);
    }

    public function test_admin_can_delete_indicator_without_cutoffs(): void
    {
        $indicatorId = $this->seedBareIndicator();

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $this->year])
            ->delete('/admin/indicadores-comges/'.$indicatorId)
            ->assertRedirect(route('voyager.compromisos.index'))
            ->assertSessionHas('message', 'Indicador eliminado correctamente.');

        $this->assertDatabaseMissing('com_indicators', ['id' => $indicatorId]);
    }

    /**
     * @return array{
     *     indicator_id: int,
     *     cutoff_link_id: int,
     *     action_id: int,
     *     sensitivity_table_id: int,
     *     setting_id: int,
     *     ambito_assignment_id: int
     * }
     */
    private function seedParametrizedIndicator(?int $yearId = null): array
    {
        $yearId ??= (int) $this->year->id;
        $suffix = bin2hex(random_bytes(4));

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1]),
            'nombre_corte' => 'Corte '.$suffix,
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => 1,
            'clave' => 'C-'.$suffix,
            'nombre' => 'Compromiso',
            'descripcion' => null,
            'orden' => 1,
            'year_id' => $yearId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'IND-'.$suffix,
            'nombre' => 'Indicador parametrizado',
            'ponderacion_indicador' => 100,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffLinkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actionId = (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $cutoffLinkId,
            'numero' => '1',
            'ponderacion_accion' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_evaluations')->insert([
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => $this->dependencyId,
            'tipo_evaluacion' => 'AUTOEVALUACION',
            'numerador' => 3,
            'denominador' => 4,
            'resultado' => 3,
            'cumplimiento' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_verifiable_documents')->insert([
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => $this->dependencyId,
            'archivos' => '[]',
            'descripcion' => 'MV test',
            'verifiable_type' => 'EVALUACION',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sensitivityTableId = (int) DB::table('com_sensitivity_tables')->insertGetId([
            'com_action_id' => $actionId,
            'meta_numero' => 90,
            'valores' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_sensitivity_dependencies')->insert([
            'com_sensitivity_table_id' => $sensitivityTableId,
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => $this->dependencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_visualizations')->insert([
            'com_indicator_cutoff_id' => $cutoffLinkId,
            'cen_hie_dependency_id' => $this->dependencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $settingId = (int) DB::table('com_indicator_settings')->insertGetId([
            'cen_hie_dependency_id' => $this->dependencyId,
            'com_indicator_id' => $indicatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_indicator_setting_user')->insert([
            'com_indicator_setting_id' => $settingId,
            'user_id' => $this->admin->id,
        ]);

        $ambitoId = (int) DB::table('indicator_ambitos')->insertGetId([
            'slug' => 'amb-'.$suffix,
            'nombre' => 'Ámbito '.$suffix,
            'orden' => 1,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ambitoAssignmentId = (int) DB::table('indicator_ambito_assignments')->insertGetId([
            'indicator_ambito_id' => $ambitoId,
            'indicatable_type' => ComIndicator::class,
            'indicatable_id' => $indicatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('indicator_activities')->insert([
            'indicator_type' => 'comges',
            'indicator_id' => $indicatorId,
            'action' => 'updated',
            'description' => 'Cambio de prueba',
            'metadata' => null,
            'user_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        return [
            'indicator_id' => $indicatorId,
            'cutoff_link_id' => $cutoffLinkId,
            'action_id' => $actionId,
            'sensitivity_table_id' => $sensitivityTableId,
            'setting_id' => $settingId,
            'ambito_assignment_id' => $ambitoAssignmentId,
        ];
    }

    private function seedBareIndicator(): int
    {
        $suffix = bin2hex(random_bytes(4));

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => 1,
            'clave' => 'CB-'.$suffix,
            'nombre' => 'Compromiso bare',
            'descripcion' => null,
            'orden' => 1,
            'year_id' => $this->year->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'IND-B-'.$suffix,
            'nombre' => 'Indicador sin cortes',
            'ponderacion_indicador' => 100,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantIndicadorBreadPermissions(Role $role, bool $edit = true): void
    {
        $actions = ['browse', 'read'];
        if ($edit) {
            $actions = array_merge($actions, ['edit', 'add', 'delete']);
        }

        foreach ($actions as $action) {
            $permissionId = Permission::firstOrCreate(
                ['key' => $action.'_com_indicators'],
                ['table_name' => 'com_indicators']
            )->id;
            $role->permissions()->syncWithoutDetaching([$permissionId]);
        }
    }

    private function seedIndicadoresBread(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        $dataType = DataType::unguarded(function (): DataType {
            return DataType::query()->updateOrCreate(
                ['slug' => 'indicadores-comges'],
                [
                    'name' => 'com_indicators',
                    'display_name_singular' => 'Indicador COMGES',
                    'display_name_plural' => 'Indicadores COMGES',
                    'model_name' => ComIndicator::class,
                    'controller' => ComIndicatorController::class,
                    'generate_permissions' => 0,
                    'server_side' => 0,
                    'details' => json_encode([]),
                ]
            );
        });

        DataRow::unguarded(function () use ($dataType): void {
            foreach (
                [
                    ['field' => 'com_commitment_id', 'type' => 'read_only', 'order' => 2],
                    ['field' => 'clave', 'type' => 'text', 'order' => 3],
                    ['field' => 'nombre', 'type' => 'text', 'order' => 4],
                    ['field' => 'ponderacion_indicador', 'type' => 'number', 'order' => 5],
                ] as $row
            ) {
                DataRow::query()->updateOrCreate(
                    [
                        'data_type_id' => $dataType->id,
                        'field' => $row['field'],
                    ],
                    [
                        'type' => $row['type'],
                        'display_name' => $row['field'],
                        'required' => 0,
                        'browse' => 1,
                        'read' => 1,
                        'edit' => 1,
                        'add' => 1,
                        'delete' => 0,
                        'details' => '{}',
                        'order' => $row['order'],
                    ]
                );
            }
        });
    }

    private function seedComgesRelatedDataTypes(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        DataType::unguarded(function (): void {
            DataType::query()->updateOrCreate(
                ['name' => 'com_verifiable_documents'],
                [
                    'slug' => 'medios-verificacion',
                    'display_name_singular' => 'Medio de verificación',
                    'display_name_plural' => 'Medios de verificación',
                    'model_name' => ComVerifiableDocument::class,
                    'controller' => null,
                    'generate_permissions' => 0,
                    'server_side' => 0,
                    'details' => json_encode([]),
                ]
            );

            DataType::query()->updateOrCreate(
                ['name' => 'com_actions'],
                [
                    'slug' => 'acciones',
                    'display_name_singular' => 'Acción',
                    'display_name_plural' => 'Acciones',
                    'model_name' => ComAction::class,
                    'controller' => null,
                    'generate_permissions' => 0,
                    'server_side' => 0,
                    'details' => json_encode([]),
                ]
            );
        });
    }

    private function seedDependency(): int
    {
        return (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-DEL',
            'nombre' => 'Servicio eliminación',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array{indicator_id: int, action_id: int, sensitivity_table_id: int} $seed */
    private function seedSecondDependencySatellites(array $seed): int
    {
        $ss2 = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-DEL-2',
            'nombre' => 'Servicio eliminación 2',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $settingId = (int) DB::table('com_indicator_settings')->insertGetId([
            'cen_hie_dependency_id' => $ss2,
            'com_indicator_id' => $seed['indicator_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('com_indicator_setting_user')->insert([
            'com_indicator_setting_id' => $settingId,
            'user_id' => $this->referente->id,
        ]);
        DB::table('com_evaluations')->insert([
            'com_action_id' => $seed['action_id'],
            'cen_hie_dependency_id' => $ss2,
            'tipo_evaluacion' => 'AUTOEVALUACION',
            'numerador' => 1,
            'denominador' => 1,
            'resultado' => 1,
            'cumplimiento' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('com_verifiable_documents')->insert([
            'com_action_id' => $seed['action_id'],
            'cen_hie_dependency_id' => $ss2,
            'archivos' => '[]',
            'descripcion' => 'MV SS2',
            'verifiable_type' => 'EVALUACION',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('com_sensitivity_dependencies')->insert([
            'com_sensitivity_table_id' => $seed['sensitivity_table_id'],
            'com_action_id' => $seed['action_id'],
            'cen_hie_dependency_id' => $ss2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $ss2;
    }
}
