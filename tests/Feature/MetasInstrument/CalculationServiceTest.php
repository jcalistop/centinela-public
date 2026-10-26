<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetDataField;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\MetCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private CenCenter $center;

    private MetIndicator $indicator;

    private MetDataField $fieldA;

    private MetDataField $fieldB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $user = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($adminRole)->save();

        $this->actingAs($user)->withSession(['indicator_year' => $this->year]);

        $this->createCenterCatalogDependencies();

        $this->center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-001',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Test',
            'nombre_corto' => 'HospTest',
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

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-CALC-01',
            'nombre' => 'Indicador cálculo',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 40,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        $this->fieldA = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'orden' => 0,
        ]));

        $this->fieldB = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'orden' => 1,
        ]));

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $this->indicator->id,
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
            'description' => '(A / B) * 100',
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'seleccionado' => true,
            'ponderacion' => 100,
        ]));
    }

    /** @test */
    public function calcula_resultado_por_indicador_y_periodo(): void
    {
        $this->createMonthlyData($this->fieldA->id, [1 => 10, 2 => 20, 3 => 30]);
        $this->createMonthlyData($this->fieldB->id, [1 => 20, 2 => 40, 3 => 60]);

        $service = app(MetCalculationService::class);

        $result = $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-03');

        $this->assertSame('50.0000', number_format((float) $result->resultado, 4, '.', ''));
        $this->assertTrue($result->cumple_meta);
        $this->assertNull($result->error_message);
    }

    /** @test */
    public function calcula_resultado_global_de_establecimiento(): void
    {
        $this->createMonthlyData($this->fieldA->id, [1 => 10, 2 => 20, 3 => 30]);
        $this->createMonthlyData($this->fieldB->id, [1 => 20, 2 => 40, 3 => 60]);

        $service = app(MetCalculationService::class);

        $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-03');
        $establishmentResult = $service->calculateEstablishmentResult($this->center->id, $this->year->id, 1, 'MES-03', $this->indicatorType->id);

        $this->assertSame('100.0000', number_format((float) $establishmentResult->resultado_global, 4, '.', ''));
        $this->assertSame(1, $establishmentResult->n_seleccionados);
        $this->assertSame(1, $establishmentResult->n_calculados);
    }

    /** @test */
    public function division_por_cero_guarda_resultado_nulo_y_error(): void
    {
        $this->createMonthlyData($this->fieldA->id, [1 => 10]);
        $this->createMonthlyData($this->fieldB->id, [1 => 0]);

        $service = app(MetCalculationService::class);

        $result = $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-01');

        $this->assertNull($result->resultado);
        $this->assertNull($result->cumple_meta);
        $this->assertSame(MetResult::MESSAGE_DIVISOR_CERO, $result->error_message);
    }

    /** @test */
    public function subanual_cero_sobre_cero_persiste_resultado_cero(): void
    {
        $this->createMonthlyData($this->fieldA->id, [12 => 0]);
        $this->createMonthlyData($this->fieldB->id, [12 => 0]);

        $service = app(MetCalculationService::class);

        $result = $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-12');

        $this->assertSame(0.0, (float) $result->resultado);
        $this->assertNull($result->error_message);
    }

    /** @test */
    public function anual_cero_sobre_cero_persiste_resultado_cero(): void
    {
        $service = app(MetCalculationService::class);

        $result = $service->calculateIndicatorFromAnnualCapture(
            $this->indicator,
            $this->center->id,
            $this->year->id,
            1,
            MetCalculationService::ETAPA_AUTOEVALUACION,
            0.0,
            0.0,
        );

        $this->assertSame(0.0, (float) $result->resultado);
        $this->assertNull($result->error_message);
    }

    /** @test */
    public function sin_carga_mensual_guarda_resultado_nulo_y_nd_en_error_message(): void
    {
        $service = app(MetCalculationService::class);
        $result = $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-01');

        $this->assertNull($result->resultado);
        $this->assertSame('N/D', $result->error_message);
    }

    /** @test */
    public function usa_meta_efectiva_del_centro_en_lugar_de_meta_nacional(): void
    {
        // resultado = 50, meta_nacional = 40 → cumpliría si se usa meta_nacional
        // pero meta_efectiva = 60 → NO cumple
        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update(['meta_efectiva' => 60]);

        $this->createMonthlyData($this->fieldA->id, [1 => 10, 2 => 20, 3 => 30]);
        $this->createMonthlyData($this->fieldB->id, [1 => 20, 2 => 40, 3 => 60]);

        $service = app(MetCalculationService::class);

        $result = $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-03');

        $this->assertSame('50.0000', number_format((float) $result->resultado, 4, '.', ''));
        $this->assertFalse($result->cumple_meta);
    }

    /** @test */
    public function sin_meta_efectiva_usa_meta_nacional(): void
    {
        // meta_efectiva = null → cae a meta_valor = 40; resultado = 50 → cumple
        $this->createMonthlyData($this->fieldA->id, [1 => 10, 2 => 20, 3 => 30]);
        $this->createMonthlyData($this->fieldB->id, [1 => 20, 2 => 40, 3 => 60]);

        $service = app(MetCalculationService::class);

        $result = $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-03');

        $this->assertTrue($result->cumple_meta);
    }

    /** @test */
    public function ultimo_dato_usa_el_valor_del_ultimo_mes_cargado(): void
    {
        // Campo A con aggregation_type = 'ultimo_dato': meses 1=10, 2=20, 3=30 → debe usar mes 3 = 30
        MetDataField::where('id', $this->fieldA->id)->update(['aggregation_type' => 'ultimo_dato']);
        // Campo B sigue con sumatoria: 20+40+60 = 120
        $this->createMonthlyData($this->fieldA->id, [1 => 10, 2 => 20, 3 => 30]);
        $this->createMonthlyData($this->fieldB->id, [1 => 20, 2 => 40, 3 => 60]);

        $service = app(MetCalculationService::class);
        $result = $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-03');

        // (30 / 60) * 100 = 50.0
        $this->assertSame('50.0000', number_format((float) $result->resultado, 4, '.', ''));
    }

    /** @test */
    public function ultimo_dato_ignora_meses_nulos_y_toma_el_ultimo_con_valor(): void
    {
        // Solo meses 1 y 2 cargados para A (mes 3 sin dato) → último dato = mes 2 = 20
        MetDataField::where('id', $this->fieldA->id)->update(['aggregation_type' => 'ultimo_dato']);
        $this->createMonthlyData($this->fieldA->id, [1 => 10, 2 => 20]);
        $this->createMonthlyData($this->fieldB->id, [1 => 20, 2 => 40, 3 => 60]);

        $service = app(MetCalculationService::class);
        $result = $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-02');

        // (20 / 40) * 100 = 50.0
        $this->assertSame('50.0000', number_format((float) $result->resultado, 4, '.', ''));
    }

    /** @test */
    public function ultimo_dato_retorna_nulo_si_no_hay_datos_en_el_periodo(): void
    {
        MetDataField::where('id', $this->fieldA->id)->update(['aggregation_type' => 'ultimo_dato']);
        // Sin datos para fieldA en el periodo
        $this->createMonthlyData($this->fieldB->id, [1 => 20, 2 => 40, 3 => 60]);

        $service = app(MetCalculationService::class);
        $result = $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-02');

        $this->assertNull($result->resultado);
    }

    /** @test */
    public function calcula_resultado_global_aplicando_umbrales(): void
    {
        $this->indicator->update([
            'umbral_inferior' => 70,
            'umbral_superior' => 95,
            'meta_valor' => 100,
        ]);

        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update([
                'ponderacion' => 25,
                'meta_efectiva' => 100,
            ]);

        $service = app(MetCalculationService::class);

        MetMonthlyData::query()->delete();
        $this->createMonthlyData($this->fieldA->id, [1 => 69]);
        $this->createMonthlyData($this->fieldB->id, [1 => 100]);
        $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-01');
        $belowLower = $service->calculateEstablishmentResult($this->center->id, $this->year->id, 1, 'MES-01', $this->indicatorType->id);
        $this->assertSame('0.0000', number_format((float) $belowLower->resultado_global, 4, '.', ''));

        MetMonthlyData::query()->delete();
        $this->createMonthlyData($this->fieldA->id, [1 => 80]);
        $this->createMonthlyData($this->fieldB->id, [1 => 100]);
        $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-01');
        $withinRange = $service->calculateEstablishmentResult($this->center->id, $this->year->id, 1, 'MES-01', $this->indicatorType->id);
        $this->assertSame('20.0000', number_format((float) $withinRange->resultado_global, 4, '.', ''));

        MetMonthlyData::query()->delete();
        $this->createMonthlyData($this->fieldA->id, [1 => 120]);
        $this->createMonthlyData($this->fieldB->id, [1 => 100]);
        $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-01');
        $aboveUpper = $service->calculateEstablishmentResult($this->center->id, $this->year->id, 1, 'MES-01', $this->indicatorType->id);
        $this->assertSame('25.0000', number_format((float) $aboveUpper->resultado_global, 4, '.', ''));
    }

    /** @test */
    public function indicator_compliance_percent_es_lineal_acotado_0_100_y_sin_umbrales(): void
    {
        $service = app(MetCalculationService::class);

        $this->indicator->update([
            'umbral_inferior' => 99.49,
            'umbral_superior' => 99.50,
            'meta_valor' => 100,
            'meta_operador' => '>=',
        ]);
        $this->indicator->refresh();

        $this->assertEqualsWithDelta(99.29, $service->indicatorCompliancePercent($this->indicator, 99.29, 100.0), 0.0001);
        $this->assertEqualsWithDelta(100.0, $service->indicatorCompliancePercent($this->indicator, 120.0, 100.0), 0.0001);
        $this->assertEqualsWithDelta(50.0, $service->indicatorCompliancePercent($this->indicator, 25.0, 50.0), 0.0001);
        $this->assertEqualsWithDelta(0.0, $service->indicatorCompliancePercent($this->indicator, -16.67, 2.0), 0.0001);
        $this->assertEqualsWithDelta(0.0, $service->indicatorCompliancePercent($this->indicator, -5.0, 100.0), 0.0001);
        $this->assertNull($service->indicatorCompliancePercent($this->indicator, 50.0, 0.0));
        $this->assertNull($service->indicatorCompliancePercent($this->indicator, 50.0, null));
    }

    /** @test */
    public function indicator_compliance_percent_respeta_polaridad_inversa_menor_igual(): void
    {
        $service = app(MetCalculationService::class);

        $this->indicator->update([
            'meta_operador' => '<=',
            'meta_valor' => 37.58,
            'umbral_inferior' => 0,
            'umbral_superior' => 100,
        ]);
        $this->indicator->refresh();

        // Caso Puerto Montt MET-19664-2026-09: 40,65 / meta 37,58 → ~92,45 % (no 100 %).
        $this->assertEqualsWithDelta(
            92.44811,
            $service->indicatorCompliancePercent($this->indicator, 40.65, 37.58),
            0.001,
        );
        $this->assertFalse($service->indicatorMeetsMeta($this->indicator, 40.65, 37.58));

        // Resultado bajo la meta → cumple → 100 %.
        $this->assertEqualsWithDelta(
            100.0,
            $service->indicatorCompliancePercent($this->indicator, 35.0, 37.58),
            0.0001,
        );
        $this->assertTrue($service->indicatorMeetsMeta($this->indicator, 35.0, 37.58));

        // Resultado 0 en polaridad inversa → 100 % (DR-200-02).
        $this->assertEqualsWithDelta(
            100.0,
            $service->indicatorCompliancePercent($this->indicator, 0.0, 37.58),
            0.0001,
        );

        // Meta no evaluable.
        $this->assertNull($service->indicatorCompliancePercent($this->indicator, 40.65, 0.0));
        $this->assertNull($service->indicatorCompliancePercent($this->indicator, 40.65, null));
    }

    /** @test */
    public function indicator_compliance_percent_polaridad_directa_mantiene_paridad_historica(): void
    {
        $service = app(MetCalculationService::class);

        $this->indicator->update([
            'meta_operador' => '>=',
            'meta_valor' => 80,
        ]);
        $this->indicator->refresh();

        $this->assertEqualsWithDelta(
            100.0,
            $service->indicatorCompliancePercent($this->indicator, 85.60, 80.0),
            0.0001,
        );
        $this->assertEqualsWithDelta(
            50.0,
            $service->indicatorCompliancePercent($this->indicator, 40.0, 80.0),
            0.0001,
        );
    }

    /** @test */
    public function weighted_compliance_from_result_usa_polaridad_inversa_en_el_aporte(): void
    {
        $service = app(MetCalculationService::class);

        $this->indicator->update([
            'meta_operador' => '<=',
            'meta_valor' => 37.58,
            'umbral_inferior' => 0,
            'umbral_superior' => 100,
        ]);
        $this->indicator->refresh();

        $configuration = MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->firstOrFail();
        $configuration->update([
            'meta_efectiva' => 37.58,
            'ponderacion' => 50,
            'seleccionado' => true,
        ]);
        $configuration->metIndicator()->associate($this->indicator);
        $configuration->refresh();

        // 40,65 → ~92,45 % cumplimiento → aporte ≈ 46,22 de 50.
        $aporte = $service->weightedComplianceFromResult($configuration, 40.65);
        $this->assertEqualsWithDelta(46.22, $aporte, 0.01);

        $aporteCumple = $service->weightedComplianceFromResult($configuration, 35.0);
        $this->assertEqualsWithDelta(50.0, $aporteCumple, 0.01);
    }

    /** @test */
    public function indicator_scaled_compliance_percent_aplica_umbrales_y_permite_iguales(): void
    {
        $service = app(MetCalculationService::class);

        $this->indicator->update([
            'umbral_inferior' => 99.49,
            'umbral_superior' => 99.50,
            'meta_valor' => 100,
        ]);

        $this->assertSame(0.0, $service->indicatorScaledCompliancePercent($this->indicator, 99.29, 100.0));
        $this->assertSame(100.0, $service->indicatorScaledCompliancePercent($this->indicator, 99.75, 100.0));
        $this->assertSame(100.0, $service->indicatorScaledCompliancePercent($this->indicator, 130.0, 100.0));

        $this->indicator->update([
            'umbral_inferior' => 95,
            'umbral_superior' => 95,
        ]);

        $this->assertSame(0.0, $service->indicatorScaledCompliancePercent($this->indicator, 94.0, 100.0));
        $this->assertSame(100.0, $service->indicatorScaledCompliancePercent($this->indicator, 95.0, 100.0));
    }

    /** @test */
    public function aporte_ponderado_redondea_a_dos_decimales_antes_del_global(): void
    {
        $service = app(MetCalculationService::class);

        $this->indicator->update([
            'umbral_inferior' => 75,
            'umbral_superior' => 95,
            'meta_valor' => 80,
        ]);

        $configuration = MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->firstOrFail();
        $configuration->metIndicator()->associate($this->indicator);
        $configuration->ponderacion = 10;
        $configuration->meta_efectiva = 80;

        // 75/80×100 = 93.75 → aporte 9.375 → 9.38
        $this->assertSame(9.38, $service->weightedComplianceFromResult($configuration, 75.0));

        // 70.2/80×100 = 87.75 → aporte 8.775 → 8.78
        $this->assertSame(8.78, $service->weightedComplianceFromResult($configuration, 70.2));

        $this->assertEqualsWithDelta(
            18.16,
            $service->weightedComplianceFromResult($configuration, 75.0)
                + $service->weightedComplianceFromResult($configuration, 70.2),
            0.0001,
        );
    }

    /** @test */
    public function resultado_global_anual_incluye_indicadores_sin_fila_anual(): void
    {
        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->update(['ponderacion' => 15, 'meta_efectiva' => 100]);

        $indicator2 = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-CALC-02',
            'nombre' => 'Indicador cálculo 2',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 100,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator2->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator2->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'orden' => 1,
        ]));

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $indicator2->id,
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
            'description' => '(A / B) * 100',
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $indicator2->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'seleccionado' => true,
            'ponderacion' => 15,
            'meta_efectiva' => 100,
        ]));

        $etapa = MetCalculationService::ETAPA_AUTOEVALUACION;

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-03',
            'etapa' => $etapa,
            'resultado' => 100,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $indicator2->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-05',
            'etapa' => $etapa,
            'resultado' => 100,
        ]));

        $service = app(MetCalculationService::class);
        $establishmentResult = $service->calculateEstablishmentResult(
            $this->center->id,
            $this->year->id,
            1,
            'ANUAL',
            $this->indicatorType->id,
        );

        $this->assertSame('30.0000', number_format((float) $establishmentResult->resultado_global, 4, '.', ''));
        $this->assertSame(2, $establishmentResult->n_calculados);
    }

    /** @test */
    public function indicador_sin_meta_efectiva_ni_meta_valor_no_aporta_resultado_global(): void
    {
        $this->indicator->update(['meta_valor' => null]);
        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update(['meta_efectiva' => null]);

        $this->createMonthlyData($this->fieldA->id, [1 => 10, 2 => 20, 3 => 30]);
        $this->createMonthlyData($this->fieldB->id, [1 => 20, 2 => 40, 3 => 60]);

        $service = app(MetCalculationService::class);
        $indicatorResult = $service->calculateIndicatorPeriod($this->indicator, $this->center->id, $this->year->id, 1, 'MES-03');
        $establishmentResult = $service->calculateEstablishmentResult($this->center->id, $this->year->id, 1, 'MES-03', $this->indicatorType->id);

        $this->assertNotNull($indicatorResult->resultado);
        $this->assertNull($establishmentResult->resultado_global);
        $this->assertSame(0, $establishmentResult->n_calculados);
    }

    private function createMonthlyData(int $fieldId, array $valuesByMonth): void
    {
        foreach ($valuesByMonth as $month => $value) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $fieldId,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => $month,
                'valor' => $value,
            ]));
        }
    }

    private function createCenterCatalogDependencies(): void {}
}
