<?php

namespace Tests\Feature\MetasInstrument;

use App\Livewire\MetasInstrument\IndicatorCatalogComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetDataField;
use App\Models\MetFieldSsConfig;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetIndicatorReferente;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\Dashboard\NonAdminDashboardService;
use App\Services\Metas\MetIndicatorCalculationErrorService;
use App\Services\Metas\MetIndicatorDesempenoByEstablishmentService;
use App\Services\Metas\MetIndicatorFormulaIssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Mockery;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class MetCalculationErrorAlertTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private User $controlGestion;

    private User $referente;

    private MetIndicator $indicator;

    private MetDataField $fieldA;

    private MetDataField $fieldB;

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

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->referente->role()->associate($referenteRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'habilitado' => true,
            'etapa_vigente' => 'AUTOEVALUACION',
        ]));

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'MET-18834-2025-12',
            'nombre' => 'Llamadas al 131 contestadas en menos de 10 segundos',
            'estado' => true,
            'orden' => 1,
            'meta_valor' => 80,
            'meta_operador' => '>=',
        ]));

        $this->fieldA = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 1,
            'variable_key' => 'A',
        ]));

        $this->fieldB = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 2,
            'variable_key' => 'B',
        ]));

        $this->seedValidFormulaForIndicator();
    }

    private function seedValidFormulaForIndicator(): void
    {
        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $this->indicator->id,
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
            ],
            'description' => 'A / B',
        ]));
    }

    /** @test */
    public function catalogo_muestra_alerta_error_calculo_para_referente(): void
    {
        $this->seedCalculationErrorForIndicator();
        $this->seedReferenteAssignment();

        $this->actingAs($this->referente)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('metas-catalog-alerts', false)
            ->assertSee('metas-calculation-error-alert', false)
            ->assertSee('Error en resultados', false);
    }

    /** @test */
    public function catalogo_muestra_config_y_error_cuando_coexisten(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
        ]));

        $this->seedCalculationErrorForIndicator(withCompleteConfig: false);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('Carga de datos', false)
            ->assertSee('Error en resultados', false);
    }

    /** @test */
    public function ficha_muestra_banner_con_error_de_calculo(): void
    {
        $this->seedCalculationErrorForIndicator();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicators.show', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]))
            ->assertOk()
            ->assertSee('metas-calculation-error-alert', false)
            ->assertSee('metas-calculation-error-banner', false)
            ->assertSee('Error en resultados', false)
            ->assertSee('id="tab-desempeno"', false)
            ->assertDontSee('Ver desempeño por establecimiento', false);
    }

    /** @test */
    public function dashboard_incluye_alerta_metas_por_error_de_calculo(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);
        $this->seedCalculationErrorForIndicator();

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => $this->indicatorType->route,
            'enabled' => true,
        ]));

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null],
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = User::unguarded(fn () => User::create([
            'name' => 'CG Dashboard',
            'email' => 'cg-met-alert@test.local',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($role)->save();

        $this->actingAs($user)->withSession(['indicator_year' => $this->year]);

        $payload = app(NonAdminDashboardService::class)->forUser($user);
        $alertas = $payload['alertas'] ?? [];

        $this->assertNotEmpty($alertas);
        $metasAlert = collect($alertas)->first(fn (array $alert): bool => str_contains($alert['title'], 'MET-18834-2025-12'));
        $this->assertNotNull($metasAlert);
        $this->assertStringContainsString('Error en resultados', $metasAlert['body']);
        $this->assertStringContainsString('#tab-desempeno', $metasAlert['url']);
    }

    /** @test */
    public function servicio_detecta_error_por_agregacion_sin_error_message_persistido(): void
    {
        $this->seedCompleteSsConfigForIndicator();

        $center = $this->createCenterForTests();

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $center->id,
            'aplica' => true,
            'seleccionado' => true,
        ]));

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 5,
            'valor' => 20,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-05',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 10,
            'denominador' => 0,
            'resultado' => null,
            'error_message' => null,
        ]));

        $service = app(MetIndicatorCalculationErrorService::class);

        $this->assertTrue($service->hasErrorsForIndicator((int) $this->indicator->id, 1, $this->year->id));
        $summary = $service->summaryForIndicator((int) $this->indicator->id, 1, $this->year->id);
        $this->assertNotNull($summary);
        $this->assertSame(MetResult::MESSAGE_DIVISOR_CERO, $summary['message']);
    }

    /** @test */
    public function catalogo_muestra_alerta_cuando_error_solo_aparece_por_agregacion(): void
    {
        $this->seedAggregatedCalculationErrorForIndicator();
        $this->seedReferenteAssignment();

        $this->actingAs($this->referente)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('Error en resultados', false);
    }

    /** @test */
    public function catalogo_muestra_alerta_cuando_falta_formula(): void
    {
        MetFormula::query()->where('met_indicator_id', $this->indicator->id)->delete();
        $this->indicator->update(['clave' => 'MET-18834-2025-07']);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('metas-formula-issue-alert', false)
            ->assertSee('Fórmula incompleta', false);
    }

    /** @test */
    public function catalogo_muestra_alerta_cuando_formula_referencia_variable_inexistente(): void
    {
        MetFormula::query()->where('met_indicator_id', $this->indicator->id)->update([
            'expression' => [
                ['type' => 'var', 'key' => 'Z'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
            ],
            'description' => 'Z / B',
        ]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('metas-formula-issue-alert', false)
            ->assertSee('variables no definidas', false);
    }

    /** @test */
    public function ficha_muestra_banner_cuando_falta_formula(): void
    {
        MetFormula::query()->where('met_indicator_id', $this->indicator->id)->delete();
        $this->indicator->update(['clave' => 'MET-18834-2025-07']);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicators.show', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]))
            ->assertOk()
            ->assertSee('metas-formula-issue-alert', false)
            ->assertSee('Sin fórmula de cálculo definida', false);
    }

    /** @test */
    public function dashboard_incluye_alerta_metas_por_formula_incompleta(): void
    {
        config()->set('centinela.dashboard_non_admin.enabled', true);
        MetFormula::query()->where('met_indicator_id', $this->indicator->id)->delete();
        $this->indicator->update(['clave' => 'MET-18834-2025-07']);

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => $this->indicatorType->route,
            'enabled' => true,
        ]));

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null],
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = User::unguarded(fn () => User::create([
            'name' => 'CG Dashboard Formula',
            'email' => 'cg-met-formula@test.local',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($role)->save();

        $this->actingAs($user)->withSession(['indicator_year' => $this->year]);

        $payload = app(NonAdminDashboardService::class)->forUser($user);
        $alertas = $payload['alertas'] ?? [];

        $metasAlert = collect($alertas)->first(fn (array $alert): bool => str_contains($alert['title'], 'MET-18834-2025-07'));
        $this->assertNotNull($metasAlert);
        $this->assertStringContainsString('Fórmula incompleta', $metasAlert['body']);
    }

    /** @test */
    public function servicio_formula_detecta_faltante_e_incoherencia(): void
    {
        $service = app(MetIndicatorFormulaIssueService::class);

        MetFormula::query()->where('met_indicator_id', $this->indicator->id)->delete();
        $this->indicator->unsetRelation('formula');
        $this->indicator->load(['dataFields', 'formula']);

        $missing = $service->issueForIndicator($this->indicator);
        $this->assertNotNull($missing);
        $this->assertSame(MetIndicatorFormulaIssueService::KIND_MISSING, $missing['kind']);

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $this->indicator->id,
            'expression' => [
                ['type' => 'paren', 'value' => '('],
                ['type' => 'var', 'key' => 'A'],
            ],
            'description' => '( A',
        ]));

        $this->indicator->unsetRelation('formula');
        $this->indicator->load('formula');

        $invalid = $service->issueForIndicator($this->indicator);
        $this->assertNotNull($invalid);
        $this->assertSame(MetIndicatorFormulaIssueService::KIND_INVALID, $invalid['kind']);
    }

    /** @test */
    public function hub_carga_no_falla_cuando_error_solo_aparece_por_agregacion(): void
    {
        $this->seedAggregatedCalculationErrorForIndicator();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.carga', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]))
            ->assertOk()
            ->assertSee('metas-calculation-error-alert', false)
            ->assertSee('Error en resultados', false);
    }

    /** @test */
    public function errors_by_center_id_detecta_error_solo_por_agregacion(): void
    {
        $this->seedAggregatedCalculationErrorForIndicator();

        $center = CenCenter::query()->where('codigo', 'CEN-131-01')->firstOrFail();
        $service = app(MetIndicatorCalculationErrorService::class);

        $errors = $service->errorsByCenterId(
            (int) $this->indicator->id,
            1,
            $this->year->id,
            [(int) $center->id],
        );

        $this->assertTrue($errors->has((int) $center->id));
        $this->assertSame(MetResult::MESSAGE_DIVISOR_CERO, $errors[(int) $center->id]['message']);
    }

    /** @test */
    public function aggregated_indicator_ids_path_uses_build_batch_for_indicators(): void
    {
        $method = new \ReflectionMethod(
            MetIndicatorCalculationErrorService::class,
            'indicatorIdsWithAggregatedCalculationErrors'
        );
        $filename = $method->getFileName();
        $this->assertNotFalse($filename);

        $start = $method->getStartLine();
        $end = $method->getEndLine();
        $body = implode('', array_slice(file($filename), $start - 1, $end - $start + 1));

        $this->assertStringContainsString('buildBatchForIndicators', $body);
        $this->assertStringContainsString('preloadedDesempenoBatch', $body);
        $this->assertStringNotContainsString('buildForIndicator', $body);
    }

    /** @test */
    public function calculation_errors_reuse_preloaded_desempeno_batch_without_extra_build(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'MET-PRELOAD-01',
            'nombre' => 'Preload batch',
            'estado' => true,
            'orden' => 99,
        ]));

        $desempeno = Mockery::mock(MetIndicatorDesempenoByEstablishmentService::class);
        $desempeno->shouldNotReceive('buildBatchForIndicators');
        $desempeno->shouldNotReceive('buildForIndicator');

        $this->app->instance(MetIndicatorDesempenoByEstablishmentService::class, $desempeno);

        $service = app(MetIndicatorCalculationErrorService::class);

        $preloaded = [
            (int) $indicator->id => [
                'rows' => [
                    [
                        'cen_center_id' => 1,
                        'error' => MetResult::MESSAGE_DIVISOR_CERO,
                        'ultimo_periodo' => 'ANUAL',
                    ],
                ],
            ],
        ];

        $ids = $service->indicatorIdsWithCalculationErrors(
            [(int) $indicator->id],
            1,
            $this->year->id,
            $preloaded,
        );

        $this->assertSame([(int) $indicator->id], $ids->all());
    }

    /** @test */
    public function indicator_ids_with_calculation_errors_empty_input_returns_empty(): void
    {
        $service = app(MetIndicatorCalculationErrorService::class);

        $this->assertTrue(
            $service->indicatorIdsWithCalculationErrors([], 1, $this->year->id)->isEmpty()
        );
    }

    /** @test */
    public function persisted_errors_batch_matches_per_indicator_facade(): void
    {
        $centerA = $this->createMinimalCenCenter([
            'codigo' => 'CEN-BATCH-A',
            'nombre_oficial' => 'Centro A',
            'nombre_corto' => 'A',
        ]);
        $centerB = $this->createMinimalCenCenter([
            'codigo' => 'CEN-BATCH-B',
            'nombre_oficial' => 'Centro B',
            'nombre_corto' => 'B',
        ]);

        $second = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'clave' => 'MET-BATCH-02',
            'nombre' => 'Segundo indicador',
            'estado' => true,
            'orden' => 2,
        ]));

        foreach (
            [
                [$this->indicator->id, $centerA->id, 'MES-01', 'Error A1'],
                [$this->indicator->id, $centerA->id, 'MES-02', 'Error A2'],
                [$second->id, $centerB->id, 'MES-03', 'Error B'],
            ] as [$indicatorId, $centerId, $periodo, $message]
        ) {
            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $indicatorId,
                'cen_center_id' => $centerId,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => $periodo,
                'etapa' => 'AUTOEVALUACION',
                'numerador' => null,
                'denominador' => null,
                'resultado' => null,
                'error_message' => $message,
            ]));
        }

        $service = app(MetIndicatorCalculationErrorService::class);
        $centerIds = [(int) $centerA->id, (int) $centerB->id];
        $indicatorIds = [(int) $this->indicator->id, (int) $second->id];

        $batch = $service->persistedErrorsByIndicators($indicatorIds, 1, (int) $this->year->id, $centerIds);

        foreach ($indicatorIds as $indicatorId) {
            $this->assertEquals(
                $service->persistedErrorsByCenterId($indicatorId, 1, (int) $this->year->id, $centerIds)->all(),
                $batch->get($indicatorId, collect())->all(),
            );
        }

        $this->assertSame(['MES-01', 'MES-02'], $batch->get((int) $this->indicator->id)[(int) $centerA->id]['periodos']);
        $this->assertSame('Error A1', $batch->get((int) $this->indicator->id)[(int) $centerA->id]['message']);
        $this->assertFalse($batch->get((int) $this->indicator->id)->has((int) $centerB->id));
        $this->assertSame(['MES-03'], $batch->get((int) $second->id)[(int) $centerB->id]['periodos']);
    }

    /** @test */
    public function build_batch_met_results_queries_do_not_grow_with_indicator_count(): void
    {
        $center = $this->createMinimalCenCenter([
            'codigo' => 'CEN-INV-RESULTS',
            'nombre_oficial' => 'Centro invariancia',
            'nombre_corto' => 'Inv',
        ]);

        $makeIndicator = function (int $orden) use ($center): MetIndicator {
            $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $this->indicatorType->id,
                'year_id' => $this->year->id,
                'clave' => 'MET-INV-'.$orden,
                'nombre' => 'Invariancia '.$orden,
                'estado' => true,
                'orden' => $orden,
            ]));

            $fieldA = MetDataField::unguarded(fn () => MetDataField::create([
                'met_indicator_id' => $indicator->id,
                'label' => 'A',
                'componente' => 'numerador',
                'input_type' => 'mensual',
                'aggregation_type' => 'sumatoria',
                'orden' => 1,
                'variable_key' => 'A',
            ]));
            MetDataField::unguarded(fn () => MetDataField::create([
                'met_indicator_id' => $indicator->id,
                'label' => 'B',
                'componente' => 'denominador',
                'input_type' => 'mensual',
                'aggregation_type' => 'sumatoria',
                'orden' => 2,
                'variable_key' => 'B',
            ]));

            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $center->id,
                'aplica' => true,
                'seleccionado' => true,
            ]));

            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $indicator->id,
                'cen_center_id' => $center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => 'MES-01',
                'etapa' => 'AUTOEVALUACION',
                'numerador' => 1,
                'denominador' => 0,
                'resultado' => null,
                'error_message' => 'Error: divisor cero',
            ]));

            MetFormula::unguarded(fn () => MetFormula::create([
                'met_indicator_id' => $indicator->id,
                'expression' => [
                    ['type' => 'var', 'key' => 'A'],
                    ['type' => 'op', 'value' => '/'],
                    ['type' => 'var', 'key' => 'B'],
                ],
                'description' => 'A / B',
            ]));

            unset($fieldA);

            return $indicator->fresh(['dataFields', 'indicatorType', 'formula']);
        };

        $indicators = collect(range(1, 10))->map(fn (int $orden) => $makeIndicator($orden));

        $countMetResults = function (int $take) use ($indicators): int {
            $this->app->forgetInstance(MetIndicatorDesempenoByEstablishmentService::class);
            $this->app->forgetInstance(MetIndicatorCalculationErrorService::class);

            DB::flushQueryLog();
            DB::enableQueryLog();

            $subset = MetIndicator::query()
                ->whereIn('id', $indicators->take($take)->pluck('id')->all())
                ->with(['dataFields', 'indicatorType', 'formula'])
                ->orderBy('orden')
                ->get();

            app(MetIndicatorDesempenoByEstablishmentService::class)->buildBatchForIndicators(
                $subset,
                1,
                (int) $this->year->id,
            );

            $log = DB::getQueryLog();
            DB::disableQueryLog();

            return collect($log)->filter(
                fn (array $entry): bool => (bool) preg_match(
                    '/(?:from|join|into)\s+[\'"`]?met_results/i',
                    $entry['query'],
                ),
            )->count();
        };

        $with5 = $countMetResults(5);
        $with10 = $countMetResults(10);

        $this->assertLessThanOrEqual(3, $with5, 'met_results con 5 indicadores: '.$with5);
        $this->assertLessThanOrEqual(3, $with10, 'met_results con 10 indicadores: '.$with10);
        $this->assertLessThanOrEqual(
            $with5 + 1,
            $with10,
            'met_results no debe crecer con indicadores (tol ±1). 5='.$with5.' 10='.$with10,
        );
    }

    /** @test */
    public function servicio_no_marca_nd_ni_sin_casos_como_error_de_calculo(): void
    {
        $center = $this->createCenterForTests();

        foreach (['N/D', MetResult::MESSAGE_SIN_CASOS] as $index => $message) {
            MetResult::unguarded(fn () => MetResult::create([
                'met_indicator_id' => $this->indicator->id,
                'cen_center_id' => $center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'periodo' => sprintf('MES-%02d', $index + 1),
                'etapa' => 'AUTOEVALUACION',
                'numerador' => null,
                'denominador' => null,
                'resultado' => null,
                'error_message' => $message,
            ]));
        }

        $service = app(MetIndicatorCalculationErrorService::class);

        $this->assertFalse($service->hasErrorsForIndicator((int) $this->indicator->id, 1, $this->year->id));
        $this->assertNull($service->summaryForIndicator((int) $this->indicator->id, 1, $this->year->id));
    }

    private function seedReferenteAssignment(): void
    {
        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referente->id,
        ]));
    }

    private function seedAggregatedCalculationErrorForIndicator(): void
    {
        $this->seedCompleteSsConfigForIndicator();

        $center = $this->createCenterForTests();

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $center->id,
            'aplica' => true,
            'seleccionado' => true,
        ]));

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 5,
            'valor' => 20,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-05',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 10,
            'denominador' => 0,
            'resultado' => null,
            'error_message' => null,
        ]));
    }

    private function seedCalculationErrorForIndicator(bool $withCompleteConfig = true): void
    {
        if ($withCompleteConfig) {
            $this->seedCompleteSsConfigForIndicator();
        }

        $center = $this->createCenterForTests();

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $center->id,
            'aplica' => true,
            'seleccionado' => true,
        ]));

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 5,
            'valor' => 20,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-05',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 10,
            'denominador' => 0,
            'resultado' => null,
            'error_message' => 'Error: divisor cero',
        ]));
    }

    private function seedCompleteSsConfigForIndicator(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
        ]));

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $this->fieldB->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'linea_base',
            'aggregation_type' => null,
        ]));
    }

    private function createCenterForTests(): CenCenter
    {
        return $this->createMinimalCenCenter([
            'codigo' => 'CEN-131-01',
            'nombre_oficial' => 'Hospital 131 Test',
            'nombre_corto' => 'Hosp131',
        ]);
    }
}
