<?php

namespace Tests\Feature;

use App\Models\ComIndicatorCutoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComIndicatorCutoffSensitivitySelectionTest extends TestCase
{
    use RefreshDatabase;

    private int $cenHieDependencyId;

    private int $otherCenHieDependencyId;

    private int $comIndicatorCutoffId;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = bin2hex(random_bytes(4));

        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'comges-test-'.$suffix,
            'nombre_indicador' => 'COMGES Test Type '.$suffix,
            'descripcion_indicador' => null,
            'icon_class' => null,
            'route' => 'compromisos-test-'.$suffix,
            'view' => null,
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearId = (int) DB::table('years')->insertGetId([
            'ano' => 2025,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->cenHieDependencyId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-1-'.$suffix,
            'nombre' => 'Servicio 1 '.$suffix,
            'tipo_dependencia' => 'SERVICIO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->otherCenHieDependencyId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-2-'.$suffix,
            'nombre' => 'Servicio 2 '.$suffix,
            'tipo_dependencia' => 'SERVICIO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1, 2, 3]),
            'nombre_corte' => 'Corte I '.$suffix,
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'K1-'.$suffix,
            'nombre' => 'Compromiso',
            'descripcion' => null,
            'orden' => 0,
            'year_id' => $yearId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'I1-'.$suffix,
            'nombre' => 'Indicador',
            'ponderacion_indicador' => 1,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->comIndicatorCutoffId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 1,
            'promedio_pais' => null,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_actions')->insert([
            'com_indicator_cutoff_id' => $this->comIndicatorCutoffId,
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
    }

    private function loadCutoff(): ComIndicatorCutoff
    {
        return ComIndicatorCutoff::query()
            ->with(['comActions.comSensitivityTables.comSensitivityDependencies'])
            ->findOrFail($this->comIndicatorCutoffId);
    }

    public function test_returns_false_when_action_has_no_sensitivity_tables(): void
    {
        $this->assertFalse(
            $this->loadCutoff()->hasSensitivityTableSelectionPendingForDependency($this->cenHieDependencyId)
        );
    }

    public function test_returns_true_when_tables_exist_but_none_selected_for_dependency(): void
    {
        $actionId = (int) DB::table('com_actions')->where('com_indicator_cutoff_id', $this->comIndicatorCutoffId)->value('id');

        DB::table('com_sensitivity_tables')->insert([
            'com_action_id' => $actionId,
            'meta_numero' => 90,
            'valores' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(
            $this->loadCutoff()->hasSensitivityTableSelectionPendingForDependency($this->cenHieDependencyId)
        );
    }

    public function test_returns_false_when_dependency_exists_for_servicio(): void
    {
        $actionId = (int) DB::table('com_actions')->where('com_indicator_cutoff_id', $this->comIndicatorCutoffId)->value('id');

        $tableId = (int) DB::table('com_sensitivity_tables')->insertGetId([
            'com_action_id' => $actionId,
            'meta_numero' => 90,
            'valores' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_sensitivity_dependencies')->insert([
            'com_sensitivity_table_id' => $tableId,
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(
            $this->loadCutoff()->hasSensitivityTableSelectionPendingForDependency($this->cenHieDependencyId)
        );
    }

    public function test_returns_true_when_dependency_exists_only_for_other_servicio(): void
    {
        $actionId = (int) DB::table('com_actions')->where('com_indicator_cutoff_id', $this->comIndicatorCutoffId)->value('id');

        $tableId = (int) DB::table('com_sensitivity_tables')->insertGetId([
            'com_action_id' => $actionId,
            'meta_numero' => 90,
            'valores' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_sensitivity_dependencies')->insert([
            'com_sensitivity_table_id' => $tableId,
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => $this->otherCenHieDependencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(
            $this->loadCutoff()->hasSensitivityTableSelectionPendingForDependency($this->cenHieDependencyId)
        );
    }
}
