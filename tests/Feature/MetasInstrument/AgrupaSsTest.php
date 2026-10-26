<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\IndicatorType\EtapaVigente;
use App\Jobs\MetCalculateResultsJob;
use App\Livewire\MetasInstrument\CenterSelectionComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterAdosado;
use App\Models\MetCenterInclusion;
use App\Models\MetDataField;
use App\Models\MetEstablishmentResult;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetAutoevaluacionCalculationRunner;
use App\Services\Metas\MetEstablishmentIndicatorLoadability;
use App\Services\Metas\MetIndicatorCalculationDataScopeService;
use App\Services\Metas\MetIndicatorDesempenoByEstablishmentService;
use App\Services\Metas\MetStageCalculationService;
use App\Services\MetCalculationService;
use App\Services\MetDataAggregatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use TCG\Voyager\Models\DataType;
use Tests\TestCase;

class AgrupaSsTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private IndicatorTypeSetting $setting;

    private User $controlGestion;

    private CenCenter $centerA;

    private CenCenter $centerB;

    private MetIndicator $indicator;

    /** @var MetIndicator[] */
    private array $indicators;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);

        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->setting = IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        $this->createCenterDependencies();
        $this->centerA = $this->createCenter('CEN-A', 'Hospital A');
        $this->centerB = $this->createCenter('CEN-B', 'Hospital B');

        foreach ([$this->centerA, $this->centerB] as $center) {
            MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
                'cen_center_id' => $center->id,
                'year_id' => $this->year->id,
                'indicator_type_id' => $this->indicatorType->id,
                'cen_hie_dependency_id' => 1,
            ]));
        }

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-1',
            'nombre' => 'Indicador 1',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        // Indicadores adicionales para satisfacer la validación de mínimo 4 seleccionados
        $this->indicators = [$this->indicator];
        for ($i = 2; $i <= 5; $i++) {
            $this->indicators[] = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $this->indicatorType->id,
                'clave' => "MET-{$i}",
                'nombre' => "Indicador {$i}",
                'decimales' => 2,
                'year_id' => $this->year->id,
                'estado' => true,
            ]));
        }

        DataType::unguarded(fn () => DataType::create([
            'name' => 'indicator_types',
            'slug' => 'instrumentos-evaluacion',
            'display_name_singular' => 'Tipo de indicador',
            'display_name_plural' => 'Tipos de indicador',
            'model_name' => IndicatorType::class,
            'generate_permissions' => 0,
            'server_side' => 0,
        ]));
    }

    // ─── CenterSelectionComponent ──────────────────────────────────────────────

    private function setEtapaFormulacion(): void
    {
        $this->updateMetasEtapa($this->setting, 'FORMULACION');
    }

    /** @test */
    public function agrupa_ss_saved_when_aplica_true(): void
    {
        $this->setEtapaFormulacion();
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openConfigModal', $this->centerA->id, $this->centerA->nombre_corto)
            ->set('configs', $this->buildConfigs(agrupaSsOnFirst: true))
            ->call('saveConfig')
            ->assertHasNoErrors('configGeneral');

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'aplica' => 1,
            'agrupa_ss' => 1,
            'seleccionado' => 1,
        ]);
    }

    /** @test */
    public function agrupa_ss_forced_false_when_aplica_false(): void
    {
        $this->setEtapaFormulacion();
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        // Todos seleccionados, pero el indicador 0 con aplica=false y agrupa_ss=true (intento)
        // Para satisfacer validación: los otros 3 tienen ponderacion=33.33/33.34 y seleccionado=true
        // El indicador 0 tiene aplica=false → no cuenta para seleccionados ni ponderación
        $configs = [];
        foreach ($this->indicators as $idx => $indicator) {
            $configs[$indicator->id] = [
                'aplica' => $idx !== 0,
                'agrupa_ss' => $idx === 0,  // agrupa_ss=true pero aplica=false → debe persistir false
                'seleccionado' => $idx !== 0,
                'ponderacion' => $idx === 0 ? '' : '25',
                'meta_efectiva' => '',
            ];
        }

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openConfigModal', $this->centerA->id, $this->centerA->nombre_corto)
            ->set('configs', $configs)
            ->call('saveConfig')
            ->assertHasNoErrors('configGeneral');

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->centerA->id,
            'aplica' => 0,
            'agrupa_ss' => 0,
        ]);
    }

    /** @test */
    public function reactive_clear_agrupa_ss_on_aplica_false(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $id = $this->indicator->id;

        $component = Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openConfigModal', $this->centerA->id, $this->centerA->nombre_corto)
            ->set("configs.{$id}.aplica", true)
            ->set("configs.{$id}.agrupa_ss", true)
            ->set("configs.{$id}.seleccionado", true);

        // Desmarcar aplica → debe limpiar agrupa_ss y seleccionado reactivamente
        $component->set("configs.{$id}.aplica", false);

        $configs = $component->get('configs');

        $this->assertFalse((bool) ($configs[$id]['agrupa_ss'] ?? true));
        $this->assertFalse((bool) ($configs[$id]['seleccionado'] ?? true));
    }

    /** @test */
    public function load_configs_reads_agrupa_ss_from_existing_record(): void
    {
        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->centerA->id,
            'aplica' => true,
            'agrupa_ss' => true,
            'seleccionado' => false,
            'ponderacion' => null,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openConfigModal', $this->centerA->id, $this->centerA->nombre_corto);

        $configs = $component->get('configs');

        $this->assertTrue((bool) ($configs[$this->indicator->id]['agrupa_ss'] ?? false));
    }

    // ─── MetCalculateResultsJob ────────────────────────────────────────────────

    /** @test */
    public function job_uses_ss_applicable_centers_when_agrupa_ss_true(): void
    {
        $this->createDataFieldsForIndicator($this->indicator);

        // centerA tiene agrupa_ss=true para el indicador
        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->centerA->id,
            'aplica' => true,
            'agrupa_ss' => true,
            'seleccionado' => true,
            'ponderacion' => 100.00,
        ]));

        // centerB aplica=true pero agrupa_ss=false (es el centro "extra" del SS)
        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->centerB->id,
            'aplica' => true,
            'agrupa_ss' => false,
            'seleccionado' => true,
            'ponderacion' => 100.00,
        ]));

        $capturedAdosadoIds = [];
        $mockResult = \Mockery::mock(MetResult::class)->makePartial();

        /** @var MetCalculationService&MockInterface $calcService */
        $calcService = \Mockery::mock(MetCalculationService::class);
        $calcService->shouldReceive('calculateIndicatorPeriod')
            ->andReturnUsing(function ($indicator, $centerId, $yearId, $depId, $periodo, $adosadoIds) use (&$capturedAdosadoIds, $mockResult) {
                $capturedAdosadoIds[$centerId] = $adosadoIds;

                return $mockResult;
            });
        $calcService->shouldReceive('calculateEstablishmentResult')->andReturn(new MetEstablishmentResult);

        $runner = new MetAutoevaluacionCalculationRunner(
            app(MetDataAggregatorService::class),
            $calcService,
            app(MetEstablishmentIndicatorLoadability::class),
            app(MetIndicatorCalculationDataScopeService::class),
        );

        (new MetCalculateResultsJob($this->year->id, 1, null, EtapaVigente::Autoevaluacion->value))->handle(
            $calcService,
            app(MetStageCalculationService::class),
            $runner,
        );

        // Para centerA (agrupa_ss=true), el array de adosadoIds debe contener el id de centerB
        $this->assertContains(
            $this->centerB->id,
            $capturedAdosadoIds[$this->centerA->id] ?? [],
            'centerA con agrupa_ss=true debe incluir centerB como ID adicional'
        );
    }

    /** @test */
    public function job_ignores_agrupa_ss_when_false(): void
    {
        $this->createDataFieldsForIndicator($this->indicator);

        // Solo centerA, agrupa_ss=false
        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->centerA->id,
            'aplica' => true,
            'agrupa_ss' => false,
            'seleccionado' => true,
            'ponderacion' => 100.00,
        ]));

        $capturedAdosadoIds = null;
        $mockResult = \Mockery::mock(MetResult::class)->makePartial();

        /** @var MetCalculationService&MockInterface $calcService */
        $calcService = \Mockery::mock(MetCalculationService::class);
        $calcService->shouldReceive('calculateIndicatorPeriod')
            ->andReturnUsing(function ($indicator, $centerId, $yearId, $depId, $periodo, $adosadoIds) use (&$capturedAdosadoIds, $mockResult) {
                $capturedAdosadoIds = $adosadoIds;

                return $mockResult;
            });
        $calcService->shouldReceive('calculateEstablishmentResult')->andReturn(new MetEstablishmentResult);

        $runner = new MetAutoevaluacionCalculationRunner(
            app(MetDataAggregatorService::class),
            $calcService,
            app(MetEstablishmentIndicatorLoadability::class),
            app(MetIndicatorCalculationDataScopeService::class),
        );

        (new MetCalculateResultsJob($this->year->id, 1, null, EtapaVigente::Autoevaluacion->value))->handle(
            $calcService,
            app(MetStageCalculationService::class),
            $runner,
        );

        // Sin adosados ni agrupa_ss: array vacío
        $this->assertSame([], $capturedAdosadoIds ?? []);
    }

    /** @test */
    public function job_deduplicates_adosado_that_is_also_ss_applicable(): void
    {
        $this->createDataFieldsForIndicator($this->indicator);

        // centerB es adosado de centerA y también tiene aplica=true en la config del SS
        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->centerA->id,
            'cen_center_adosado_id' => $this->centerB->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->centerA->id,
            'aplica' => true,
            'agrupa_ss' => true,
            'seleccionado' => true,
            'ponderacion' => 100.00,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->centerB->id,
            'aplica' => true,
            'agrupa_ss' => false,
            'seleccionado' => true,
            'ponderacion' => 100.00,
        ]));

        $capturedAdosadoIds = null;
        $mockResult = \Mockery::mock(MetResult::class)->makePartial();

        /** @var MetCalculationService&MockInterface $calcService */
        $calcService = \Mockery::mock(MetCalculationService::class);
        $calcService->shouldReceive('calculateIndicatorPeriod')
            ->withArgs(fn ($ind, $centerId) => $centerId === $this->centerA->id)
            ->andReturnUsing(function ($indicator, $centerId, $yearId, $depId, $periodo, $adosadoIds) use (&$capturedAdosadoIds, $mockResult) {
                $capturedAdosadoIds = $adosadoIds;

                return $mockResult;
            });
        $calcService->shouldReceive('calculateIndicatorPeriod')->andReturn($mockResult);
        $calcService->shouldReceive('calculateEstablishmentResult')->andReturn(new MetEstablishmentResult);

        $runner = new MetAutoevaluacionCalculationRunner(
            app(MetDataAggregatorService::class),
            $calcService,
            app(MetEstablishmentIndicatorLoadability::class),
            app(MetIndicatorCalculationDataScopeService::class),
        );

        (new MetCalculateResultsJob($this->year->id, 1, null, EtapaVigente::Autoevaluacion->value))->handle(
            $calcService,
            app(MetStageCalculationService::class),
            $runner,
        );

        // centerB debe aparecer exactamente una vez (sin duplicados)
        $this->assertCount(1, array_filter($capturedAdosadoIds ?? [], fn ($id) => $id === $this->centerB->id));
    }

    /** @test */
    public function desempeno_muestra_cumplimiento_cuando_agrupa_ss_y_datos_en_otro_centro(): void
    {
        $this->createDataFieldsForIndicator($this->indicator);

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'orden' => 1,
        ]));

        $this->indicator->update(['meta_valor' => 60, 'meta_operador' => '>=']);

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->centerA->id,
            'aplica' => true,
            'agrupa_ss' => true,
            'seleccionado' => true,
            'ponderacion' => 100.00,
            'meta_efectiva' => 60,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->centerB->id,
            'aplica' => true,
            'agrupa_ss' => false,
            'seleccionado' => true,
            'ponderacion' => 100.00,
            'meta_efectiva' => 60,
        ]));

        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->get()
            ->keyBy('variable_key');

        foreach (['A' => 10, 'B' => 20] as $key => $valor) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $fields[$key]->id,
                'cen_center_id' => $this->centerB->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 1,
                'valor' => $valor,
            ]));
        }

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->centerA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-01',
            'numerador' => 10,
            'denominador' => 20,
            'resultado' => 50,
            'cumple_meta' => false,
        ]));

        $payload = app(MetIndicatorDesempenoByEstablishmentService::class)->buildForIndicator(
            $this->indicator->fresh(['dataFields', 'indicatorType']),
            1,
            (int) $this->year->id,
        );

        $rowA = collect($payload['rows'])->firstWhere('cen_center_id', $this->centerA->id);

        $this->assertNotNull($rowA);
        $this->assertTrue($rowA['agrupa_ss']);
        $this->assertSame('cargado', $rowA['estado_carga']);
        $this->assertNotNull($rowA['cumplimiento']);
        $this->assertGreaterThan(0, $rowA['cumplimiento']);
    }

    /** @test */
    public function desempeno_reagrupa_formula_compleja_con_datos_del_ss(): void
    {
        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $this->indicator->id,
            'expression' => [
                ['type' => 'paren', 'value' => '('],
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '-'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'paren', 'value' => ')'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
            'description' => 'Fórmula compleja test',
        ]));

        foreach (['A', 'B'] as $orden => $variableKey) {
            MetDataField::unguarded(fn () => MetDataField::create([
                'met_indicator_id' => $this->indicator->id,
                'label' => "Variable {$variableKey}",
                'variable_key' => $variableKey,
                'componente' => 'numerador',
                'input_type' => 'mensual',
                'aggregation_type' => 'sumatoria',
                'orden' => $orden,
            ]));
        }

        foreach (
            [
                [$this->centerA, true],
                [$this->centerB, false],
            ] as [$center, $agrupaSs]
        ) {
            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $this->indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $center->id,
                'aplica' => true,
                'agrupa_ss' => $agrupaSs,
                'seleccionado' => true,
                'ponderacion' => 100.00,
            ]));
        }

        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->get()
            ->keyBy('variable_key');

        foreach (['A' => 110, 'B' => 100] as $variableKey => $valor) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $fields[$variableKey]->id,
                'cen_center_id' => $this->centerB->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 1,
                'valor' => $valor,
            ]));
        }

        foreach ([$this->centerA, $this->centerB] as $center) {
            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $this->indicator->id,
                'cen_center_id' => $center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => 'MES-01',
                'resultado' => 10,
                'cumple_meta' => true,
            ]));
        }

        $payload = app(MetIndicatorDesempenoByEstablishmentService::class)->buildForIndicator(
            $this->indicator->fresh(['dataFields', 'indicatorType']),
            1,
            (int) $this->year->id,
        );

        $rowA = collect($payload['rows'])->firstWhere('cen_center_id', $this->centerA->id);

        $this->assertNotNull($rowA);
        $this->assertTrue($rowA['agrupa_ss']);
        $this->assertSame(10.0, $rowA['resultado']);
        $this->assertNull($rowA['error']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Construye configs para los 4 indicadores: todos aplica=true, seleccionado=true, ponderacion=25.
     * Si $agrupaSsOnFirst=true, el primer indicador lleva agrupa_ss=true.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildConfigs(bool $agrupaSsOnFirst = false): array
    {
        $configs = [];

        foreach ($this->indicators as $idx => $indicator) {
            $configs[$indicator->id] = [
                'aplica' => true,
                'agrupa_ss' => $agrupaSsOnFirst && $idx === 0,
                'seleccionado' => true,
                'ponderacion' => '20',
                'meta_efectiva' => '',
            ];
        }

        return $configs;
    }

    private function createDataFieldsForIndicator(MetIndicator $indicator): void
    {
        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $indicator->id,
            'expression' => [['type' => 'var', 'key' => 'A']],
            'description' => 'Fórmula test',
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'orden' => 0,
        ]));
    }

    private function createCenter(string $codigo, string $nombre, int $depId = 1): CenCenter
    {
        return CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => $codigo,
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => $depId,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => $nombre,
            'nombre_corto' => $nombre,
            'cen_certification_id' => 1,
            'cen_adm_dependency_id' => 1,
            'nivel_atencion' => 'Primario',
            'tiene_urgencia' => 'No',
            'cen_emergency_type_id' => 1,
            'tipo_sapu' => 'No Aplica',
            'cen_provider_type_id' => 1,
            'estado_funcionamiento' => CentroEstadoFuncionamiento::VigenteEnOperacionHabitual->value,
            'nivel_complejidad' => 'Baja',
            'tipo_atencion' => 'Abierta',
        ]));
    }

    private function createCenterDependencies(): void
    {
        $this->seedLegacyCenCatalogRows();
    }
}
