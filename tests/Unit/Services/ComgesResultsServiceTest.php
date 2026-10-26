<?php

namespace Tests\Unit\Services;

use App\Enums\Comges\EvaluacionTipo;
use App\Models\ComCommitment;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\ComgesResultsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComgesResultsServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $cenHieDependencyId;

    private ComgesResultsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cenHieDependencyId = $this->seedDependency();
        $this->service = app(ComgesResultsService::class);

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
        ]);
        $this->actingAs($user);
    }

    public function test_uses_score_scale_by_year(): void
    {
        $year2024 = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 0]));
        $year2025 = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));

        $this->assertFalse(ComgesResultsService::usesScoreScale($year2024));
        $this->assertTrue(ComgesResultsService::usesScoreScale($year2025));
    }

    public function test_percentage_mode_matches_legacy_formula(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $graph = $this->seedMinimalGraph((int) $year->id, evaluacionLocal: false, includeApelacion: true);

        $commitments = $this->loadCommitments((int) $year->id);
        $results = $this->service->getGlobalResults($commitments, $year, $this->cenHieDependencyId);

        $this->assertSame('percentage', $results['mode']);
        $cutoffId = $graph['cutoff_id'];

        // puntaje_maximo: (50/100) * 10 = 5
        $this->assertEqualsWithDelta(5.0, $results['puntaje_maximo'][$cutoffId], 0.01);
        // autoevaluacion: (80/100) * (50/100) * 10 = 4
        $this->assertEqualsWithDelta(4.0, $results['autoevaluacion'][$cutoffId], 0.01);
        $this->assertEqualsWithDelta(0.0, $results['evaluacion_preliminar'][$cutoffId], 0.01);
        $this->assertEqualsWithDelta(0.0, $results['evaluacion_final'][$cutoffId], 0.01);
    }

    public function test_percentage_mode_includes_apelacion_and_evaluacion_local_cutoffs(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedMinimalGraph((int) $year->id, evaluacionLocal: true, includeApelacion: true);

        $commitments = $this->loadCommitments((int) $year->id);
        $results = $this->service->getGlobalResults($commitments, $year, $this->cenHieDependencyId);

        $this->assertCount(2, $results['cutoffs']);
    }

    public function test_score_mode_excludes_apelacion_and_evaluacion_local(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));
        $graph = $this->seedMinimalGraph((int) $year->id, evaluacionLocal: true, includeApelacion: true);

        $commitments = $this->loadCommitments((int) $year->id);
        $results = $this->service->getGlobalResults($commitments, $year, $this->cenHieDependencyId);

        $this->assertSame('score', $results['mode']);
        $this->assertArrayHasKey($graph['cutoff_id'], $results['cutoffs']);
        $this->assertArrayNotHasKey($graph['cutoff_apelacion_id'], $results['cutoffs']);
        $this->assertArrayNotHasKey($graph['cutoff_eval_local_id'], $results['cutoffs']);
    }

    public function test_score_mode_aggregates_evaluation_with_weights(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));
        $graph = $this->seedMinimalGraph(
            (int) $year->id,
            evaluacionLocal: false,
            includeApelacion: false,
            withEvaluation: false,
            indicatorPonderacion: 100,
        );

        DB::table('com_evaluations')->insert([
            'com_action_id' => $graph['action_id'],
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
            'tipo_evaluacion' => EvaluacionTipo::Autoevaluacion->value,
            'cumplimiento' => 4,
            'numerador' => 4,
            'denominador' => 1,
            'resultado' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitments = $this->loadCommitments((int) $year->id);
        $results = $this->service->getGlobalResults($commitments, $year, $this->cenHieDependencyId);

        $cutoffId = $graph['cutoff_id'];
        // puntaje: 4 * (ponderacion_corte/100) * (ponderacion_indicador/100) * (ponderacion_accion/100)
        $this->assertEqualsWithDelta(2.0, $results['autoevaluacion'][$cutoffId], 0.01);
        $this->assertEqualsWithDelta(4.0, $results['puntaje_maximo'][$cutoffId], 0.01);
    }

    public function test_latest_indicator_compliance_percent_uses_latest_available_evaluation(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));
        $graph = $this->seedMinimalGraph(
            (int) $year->id,
            evaluacionLocal: false,
            includeApelacion: false,
            withEvaluation: false,
            indicatorPonderacion: 100,
        );

        DB::table('com_evaluations')->insert([
            'com_action_id' => $graph['action_id'],
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
            'tipo_evaluacion' => EvaluacionTipo::Autoevaluacion->value,
            'cumplimiento' => 4,
            'numerador' => 4,
            'denominador' => 1,
            'resultado' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicator = $this->loadCommitments((int) $year->id)->flatMap->comIndicators->first();
        $this->assertNotNull($indicator);

        $this->assertNull($indicator->puntajePonderado('FINAL'));
        $this->assertSame(100.0, $this->service->latestIndicatorCompliancePercent($indicator, $year, $this->cenHieDependencyId));
    }

    public function test_global_results_cache_key_includes_dependency_and_year(): void
    {
        $key = ComgesResultsService::globalResultsCacheKey(99, 2024);

        $this->assertSame('comges_global_results_v4_99_2024', $key);
    }

    public function test_missing_evaluation_contributes_zero_in_percentage_mode(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $graph = $this->seedMinimalGraph((int) $year->id, withEvaluation: false);

        $commitments = $this->loadCommitments((int) $year->id);
        $results = $this->service->getGlobalResults($commitments, $year, $this->cenHieDependencyId);

        $this->assertEqualsWithDelta(0.0, $results['autoevaluacion'][$graph['cutoff_id']], 0.01);
    }

    /**
     * @return Collection<int, ComCommitment>
     */
    private function loadCommitments(int $yearId): Collection
    {
        return ComCommitment::with([
            'comIndicators.comIndicatorCutoffs.cutoff',
            'comIndicators.comIndicatorCutoffs.comActions.comEvaluations' => function ($query) {
                $query->where('cen_hie_dependency_id', $this->cenHieDependencyId);
            },
        ])->where('year_id', $yearId)->get();
    }

    /**
     * @return array{
     *     cutoff_id: int,
     *     cutoff_apelacion_id: int,
     *     cutoff_eval_local_id: int,
     *     action_id: int
     * }
     */
    private function seedMinimalGraph(
        int $yearId,
        bool $evaluacionLocal = false,
        bool $includeApelacion = false,
        bool $withEvaluation = true,
        int|float $indicatorPonderacion = 10,
    ): array {
        $suffix = bin2hex(random_bytes(4));

        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'comges-unit-'.$suffix,
            'nombre_indicador' => 'COMGES Unit',
            'descripcion_indicador' => null,
            'icon_class' => null,
            'route' => 'compromisos-unit-'.$suffix,
            'view' => null,
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffCorteId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1, 2, 3]),
            'nombre_corte' => 'Corte I',
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffApelacionId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'APELACION',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([]),
            'nombre_corte' => 'Apelación',
            'orden' => 99,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'K1',
            'nombre' => 'Compromiso',
            'descripcion' => null,
            'orden' => 0,
            'year_id' => $yearId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'I1',
            'nombre' => 'Indicador',
            'ponderacion_indicador' => $indicatorPonderacion,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffLinkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffCorteId,
            'ponderacion_corte' => 100,
            'promedio_pais' => null,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffEvalLocalId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffCorteId,
            'ponderacion_corte' => 100,
            'promedio_pais' => null,
            'evaluacion_local' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffApelacionLinkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffApelacionId,
            'ponderacion_corte' => 100,
            'promedio_pais' => null,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actionId = (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $cutoffLinkId,
            'numero' => '1',
            'meta_texto' => null,
            'tipo_medio' => null,
            'medio_verificacion' => null,
            'formula' => null,
            'fuente' => null,
            'observaciones' => null,
            'ponderacion_accion' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($evaluacionLocal) {
            DB::table('com_actions')->insert([
                'com_indicator_cutoff_id' => $cutoffEvalLocalId,
                'numero' => '2',
                'meta_texto' => null,
                'tipo_medio' => null,
                'medio_verificacion' => null,
                'formula' => null,
                'fuente' => null,
                'observaciones' => null,
                'ponderacion_accion' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($includeApelacion) {
            DB::table('com_actions')->insert([
                'com_indicator_cutoff_id' => $cutoffApelacionLinkId,
                'numero' => '3',
                'meta_texto' => null,
                'tipo_medio' => null,
                'medio_verificacion' => null,
                'formula' => null,
                'fuente' => null,
                'observaciones' => null,
                'ponderacion_accion' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($withEvaluation) {
            DB::table('com_evaluations')->insert([
                'com_action_id' => $actionId,
                'cen_hie_dependency_id' => $this->cenHieDependencyId,
                'tipo_evaluacion' => EvaluacionTipo::Autoevaluacion->value,
                'cumplimiento' => 80,
                'numerador' => 80,
                'denominador' => 100,
                'resultado' => 80,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'cutoff_id' => $cutoffCorteId,
            'cutoff_apelacion_id' => $cutoffApelacionId,
            'cutoff_eval_local_id' => $cutoffEvalLocalId,
            'action_id' => $actionId,
        ];
    }

    private function seedDependency(): int
    {
        return (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-UNIT',
            'nombre' => 'Servicio Unit Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
