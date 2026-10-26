<?php

namespace Tests\Feature\Comges;

use App\Models\ComAction;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\View\Components\Acciones\ActionsTableComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComgesActionsTableVerifiableDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_score_scale_component_exposes_verifiable_sections_for_monitoring_cutoff(): void
    {
        $admin = $this->seedAdmin();
        $action = $this->seedActionForCutoff('CORTE I', '1er Monitoreo', evaluacionLocal: false);
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $this->actingAs($admin)->withSession(['indicator_year' => $year]);

        $data = (new ActionsTableComponent($action))->render()->getData();

        $this->assertTrue($data['showVerifiableEvaluation']);
        $this->assertTrue($data['showVerifiableAppeal']);
    }

    public function test_score_scale_component_hides_appeal_when_evaluacion_local_is_true(): void
    {
        $admin = $this->seedAdmin();
        $action = $this->seedActionForCutoff('CORTE I', '1er Monitoreo', evaluacionLocal: true);
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $this->actingAs($admin)->withSession(['indicator_year' => $year]);

        $data = (new ActionsTableComponent($action))->render()->getData();

        $this->assertTrue($data['showVerifiableEvaluation']);
        $this->assertFalse($data['showVerifiableAppeal']);
    }

    public function test_year_2025_keeps_indicador_requisito_rules_for_verifiable_documents(): void
    {
        $admin = $this->seedAdmin();
        $indicadorAction = $this->seedActionForCutoff('INDICADOR', 'Indicador', evaluacionLocal: false, yearAno: 2025);
        $monitoreoAction = $this->seedActionForCutoff('CORTE I', '1er Monitoreo', evaluacionLocal: false, yearAno: 2025);
        $year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));

        $this->actingAs($admin)->withSession(['indicator_year' => $year]);

        $indicadorData = (new ActionsTableComponent($indicadorAction))->render()->getData();
        $monitoreoData = (new ActionsTableComponent($monitoreoAction))->render()->getData();

        $this->assertTrue($indicadorData['showVerifiableEvaluation']);
        $this->assertFalse($indicadorData['showVerifiableAppeal']);
        $this->assertFalse($monitoreoData['showVerifiableEvaluation']);
        $this->assertFalse($monitoreoData['showVerifiableAppeal']);
    }

    public function test_score_scale_component_shows_observaciones_oott_when_present(): void
    {
        $admin = $this->seedAdmin();
        // Año 2025 + CORTE I: sin filas de medios verificables (evita BREAD Voyager en render).
        $action = $this->seedActionForCutoff(
            'CORTE I',
            '1er Monitoreo',
            evaluacionLocal: false,
            yearAno: 2025,
            observaciones: '<p>Nota OOOT de prueba</p>',
        );
        $year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));

        $this->actingAs($admin)->withSession(['indicator_year' => $year]);

        $html = (new ActionsTableComponent($action))->render()->render();

        $this->assertStringContainsString('Observaciones OOOT', $html);
        $this->assertStringContainsString('Nota OOOT de prueba', $html);
    }

    public function test_score_scale_component_hides_observaciones_oott_when_empty(): void
    {
        $admin = $this->seedAdmin();
        $action = $this->seedActionForCutoff(
            'CORTE I',
            '1er Monitoreo',
            evaluacionLocal: false,
            yearAno: 2025,
        );
        $year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));

        $this->actingAs($admin)->withSession(['indicator_year' => $year]);

        $html = (new ActionsTableComponent($action))->render()->render();

        $this->assertStringNotContainsString('Observaciones OOOT', $html);
    }

    private function seedAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $this->seedDependency(),
        ]);
        $user->role()->associate($role)->save();

        return $user;
    }

    private function seedDependency(): int
    {
        return (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-ACTIONS-TABLE',
            'nombre' => 'Servicio actions table',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedActionForCutoff(
        string $clave,
        string $nombreCorte,
        bool $evaluacionLocal,
        int $yearAno = 2026,
        ?string $observaciones = null,
    ): ComAction {
        if (! DB::table('indicator_types')->where('id', 1)->exists()) {
            DB::table('indicator_types')->insert([
                'id' => 1,
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'route' => 'voyager.compromisos.index',
                'estado' => 1,
                'orden' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $yearId = (int) DB::table('years')->insertGetId([
            'ano' => $yearAno,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => $clave,
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1]),
            'nombre_corte' => $nombreCorte,
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => 1,
            'clave' => 'C-VERIF',
            'nombre' => 'Compromiso',
            'year_id' => $yearId,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'IND-VERIF',
            'nombre' => 'Indicador',
            'ponderacion_indicador' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorCutoffId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
            'evaluacion_local' => $evaluacionLocal ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actionId = (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $indicatorCutoffId,
            'numero' => '1',
            'ponderacion_accion' => 100,
            'observaciones' => $observaciones,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ComAction::query()
            ->with('comIndicatorCutoff.cutoff')
            ->findOrFail($actionId);
    }
}
