<?php

namespace Tests\Feature\Comges;

use App\Http\Controllers\Voyager\ComEvaluationController;
use App\Models\ComSensitivityTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class ComEvaluationSensitivityEloquentTest extends TestCase
{
    use RefreshDatabase;

    private int $dependencyId;

    private int $actionId;

    private int $sensitivityTableId;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(3));

        $this->dependencyId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-E2-'.$suffix,
            'nombre' => 'Servicio E2 '.$suffix,
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $this->dependencyId,
        ]);
        $this->user->role()->associate($role)->save();

        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'comges-e2-'.$suffix,
            'nombre_indicador' => 'COMGES E2',
            'descripcion_indicador' => null,
            'icon_class' => null,
            'route' => 'compromisos-e2-'.$suffix,
            'view' => null,
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearId = (int) DB::table('years')->insertGetId([
            'ano' => 2026,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1, 2, 3]),
            'nombre_corte' => 'Corte I E2',
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'K-E2-'.$suffix,
            'nombre' => 'Compromiso E2',
            'descripcion' => null,
            'orden' => 0,
            'year_id' => $yearId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'I-E2-'.$suffix,
            'nombre' => 'Indicador E2',
            'ponderacion_indicador' => 1,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffLinkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 1,
            'promedio_pais' => null,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actionId = (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $cutoffLinkId,
            'numero' => '1',
            'meta_texto' => null,
            'tipo_medio' => null,
            'medio_verificacion' => null,
            'formula' => null,
            'fuente' => null,
            'observaciones' => null,
            'ponderacion_accion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sensitivityTableId = (int) DB::table('com_sensitivity_tables')->insertGetId([
            'com_action_id' => $this->actionId,
            'meta_numero' => 90,
            'valores' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function get_selected_sensitivity_table_returns_null_without_ss_selection(): void
    {
        $this->actingAs($this->user);

        $table = $this->invokeGetSelectedSensitivityTable($this->actionId);

        $this->assertNull($table);
    }

    /** @test */
    public function get_selected_sensitivity_table_returns_table_when_ss_has_selection(): void
    {
        DB::table('com_sensitivity_dependencies')->insert([
            'com_sensitivity_table_id' => $this->sensitivityTableId,
            'com_action_id' => $this->actionId,
            'cen_hie_dependency_id' => $this->dependencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user);

        $table = $this->invokeGetSelectedSensitivityTable($this->actionId);

        $this->assertInstanceOf(ComSensitivityTable::class, $table);
        $this->assertSame($this->sensitivityTableId, (int) $table->id);
    }

    /** @test */
    public function create_method_does_not_use_db_table_join_for_sensitivity_check(): void
    {
        $source = file_get_contents(
            (new ReflectionMethod(ComEvaluationController::class, 'create'))->getFileName()
        );

        $this->assertIsString($source);
        $this->assertStringContainsString('getSelectedSensitivityTable', $source);
        $this->assertStringNotContainsString(
            "DB::table('com_sensitivity_dependencies')",
            $source,
        );
    }

    private function invokeGetSelectedSensitivityTable(int $comActionId): ?ComSensitivityTable
    {
        $controller = app(ComEvaluationController::class);
        $method = new ReflectionMethod(ComEvaluationController::class, 'getSelectedSensitivityTable');
        $method->setAccessible(true);

        /** @var ComSensitivityTable|null $result */
        $result = $method->invoke($controller, $comActionId);

        return $result;
    }
}
