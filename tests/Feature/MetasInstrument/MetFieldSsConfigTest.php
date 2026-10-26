<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Metas\DataFieldAggregationType;
use App\Enums\Metas\DataFieldInputType;
use App\Livewire\MetasInstrument\IndicatorCatalogComponent;
use App\Livewire\MetasInstrument\IndicatorFieldSsConfigComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetDataField;
use App\Models\MetFieldSsConfig;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetIndicatorReferente;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetFieldSsConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MetFieldSsConfigTest extends TestCase
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

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gesti?n']);
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
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-SS-01',
            'nombre' => 'Indicador config SS',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        $this->fieldA = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        $this->fieldB = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'L?nea base',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'linea_base',
            'aggregation_type' => null,
            'orden' => 1,
        ]));
    }

    /** @test */
    public function resolve_input_type_usa_override_por_ss(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'linea_base',
            'aggregation_type' => null,
        ]));

        $this->assertSame(DataFieldInputType::LineaBase, $this->fieldA->resolveInputType(1, $this->year->id));
        $this->assertNull($this->fieldA->resolveAggregationType(1, $this->year->id));
    }

    /** @test */
    public function apply_effective_types_evita_requery_en_resolve_input_type(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'trimestral',
            'aggregation_type' => 'ultimo_dato',
        ]));

        $fields = collect([$this->fieldA->fresh()]);
        $service = app(MetFieldSsConfigService::class);
        $effective = $service->applyEffectiveTypes($fields, 1, $this->year->id);
        $field = $effective->first();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertSame(DataFieldInputType::Trimestral, $field->resolveInputType(1, $this->year->id));
        $this->assertSame(
            DataFieldAggregationType::UltimoDato,
            $field->resolveAggregationType(1, $this->year->id),
        );

        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $log);
    }

    /** @test */
    public function resolve_input_type_sin_config_usa_fallback_del_campo(): void
    {
        $this->assertSame(DataFieldInputType::Mensual, $this->fieldA->resolveInputType(1, $this->year->id));
    }

    /** @test */
    public function configuration_status_none_sin_filas_ss(): void
    {
        $status = app(MetFieldSsConfigService::class)->configurationStatusForIndicator(
            $this->indicator->load('dataFields'),
            1,
            $this->year->id,
        );

        $this->assertSame(MetFieldSsConfigService::STATUS_NONE, $status);
    }

    /** @test */
    public function configuration_status_complete_con_todas_las_variables(): void
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

        $status = app(MetFieldSsConfigService::class)->configurationStatusForIndicator(
            $this->indicator->load('dataFields'),
            1,
            $this->year->id,
        );

        $this->assertSame(MetFieldSsConfigService::STATUS_COMPLETE, $status);
    }

    /** @test */
    public function control_gestion_puede_guardar_configuracion_ss(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorFieldSsConfigComponent::class, [
            'indicatorId' => $this->indicator->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->set("fieldConfigs.{$this->fieldA->id}.input_type", 'mensual')
            ->set("fieldConfigs.{$this->fieldA->id}.aggregation_type", 'ultimo_dato')
            ->set("fieldConfigs.{$this->fieldB->id}.input_type", 'linea_base')
            ->set("fieldConfigs.{$this->fieldB->id}.aggregation_type", null)
            ->call('saveConfiguration')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_field_ss_configs', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'mensual',
            'aggregation_type' => 'ultimo_dato',
        ]);
    }

    /** @test */
    public function referente_no_puede_guardar_configuracion_ss(): void
    {
        $this->actingAs($this->referente)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorFieldSsConfigComponent::class, [
            'indicatorId' => $this->indicator->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])->assertStatus(403);
    }

    /** @test */
    public function catalogo_muestra_alerta_sin_datos_cuando_config_ss_completa(): void
    {
        $this->seedCompleteSsConfigForIndicator();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('Actualizado a', false)
            ->assertSee('metas-catalog-alerts', false)
            ->assertSee('metas-catalog-sin-datos-alert', false)
            ->assertSee('Sin datos', false)
            ->assertSee('Aún no hay meses con datos cargados', false);
    }

    /** @test */
    public function catalogo_muestra_alerta_config_ss_pendiente_bajo_nombre(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('metas-catalog-alerts', false)
            ->assertSee('metas-catalog-config-alert', false)
            ->assertSee('Carga de datos', false)
            ->assertSee('Sin configuraci', false)
            ->assertDontSee('componente-corte-alerta', false);
    }

    /** @test */
    public function catalogo_muestra_ultimo_mes_cargado_en_columna_actualizado_a(): void
    {
        $this->seedCompleteSsConfigForIndicator();
        $this->seedValidFormulaForIndicator();

        $center = $this->createCenterForSsConfigTests();

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 3,
            'valor' => 10,
        ]));

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 5,
            'valor' => 20,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('Actualizado a', false)
            ->assertSee('May', false)
            ->assertSee('metas-catalog-actualizado-label', false)
            ->assertDontSee('componente-corte-alerta', false);
    }

    /** @test */
    public function catalogo_muestra_alerta_error_calculo_bajo_nombre_y_mes_en_actualizado_a(): void
    {
        $this->seedCompleteSsConfigForIndicator();

        $center = $this->createCenterForSsConfigTests();

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

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('metas-catalog-alerts', false)
            ->assertSee('metas-calculation-error-alert', false)
            ->assertSee('Error en resultados', false)
            ->assertSee('metas-catalog-actualizado-label', false)
            ->assertSee('May', false)
            ->assertDontSee('componente-corte-alerta', false);
    }

    /** @test */
    public function catalogo_muestra_columna_actualizado_a_para_referente(): void
    {
        $this->actingAs($this->referente)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('Actualizado a', false);
    }

    /** @test */
    public function vista_carga_muestra_etiqueta_mes_en_columna_actualizado_a(): void
    {
        $this->seedCompleteSsConfigForIndicator();
        $this->seedValidFormulaForIndicator();

        $center = $this->createCenterForSsConfigTests();

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

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

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.carga', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]))
            ->assertOk()
            ->assertSee('Actualizado a', false)
            ->assertSee('label-success">May</span>', false);
    }

    /** @test */
    public function vista_carga_muestra_alerta_control_gestion_sin_config_ss(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $response = $this->get(route('metas.instrument.indicador.carga', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]));

        $response->assertOk();
        $response->assertSee('Carga de datos', false);
        $response->assertSee('componente-corte-alerta', false);
        $response->assertSee('Sin configuraci', false)
            ->assertSee('entrada/agregac', false)
            ->assertSee('por SS', false);
        $response->assertSee('Tipos de entrada y agregac', false);
    }

    /** @test */
    public function vista_carga_muestra_alerta_error_calculo_por_establecimiento(): void
    {
        $this->seedCompleteSsConfigForIndicator();

        $center = $this->createCenterForSsConfigTests();

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $center->id,
            'aplica' => true,
            'seleccionado' => true,
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

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.carga', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]))
            ->assertOk()
            ->assertSee('Resultados', false)
            ->assertSee('metas-calculation-error-alert', false)
            ->assertSee('Error en resultados', false)
            ->assertSee('HospSS', false);
    }

    /** @test */
    public function vista_carga_oculta_alerta_control_gestion_con_config_completa(): void
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

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $response = $this->get(route('metas.instrument.indicador.carga', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]));

        $response->assertOk();
        $response->assertDontSee('Sin configuraci', false)
            ->assertDontSee('Tipos de entrada pendientes', false);
        $response->assertSee('Configuración de carga para su Servicio de Salud', false);
    }

    /** @test */
    public function vista_carga_muestra_alerta_para_referente_sin_config_completa(): void
    {
        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referente->id,
        ]));

        $this->actingAs($this->referente)->withSession(['indicator_year' => $this->year]);

        $response = $this->get(route('metas.instrument.indicador.carga', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]));

        $response->assertOk();
        $response->assertSee('Carga de datos', false);
        $response->assertSee('componente-corte-alerta', false);
        $response->assertSee('Sin configuraci', false)
            ->assertSee('entrada/agregac', false)
            ->assertSee('por SS', false);
        $response->assertSee('Contacte a Control de Gest', false);
        $response->assertDontSee('Tipos de entrada y agregac', false);
        $response->assertDontSee('Descargar plantilla XLSX', false);
        $response->assertDontSee('Carga manual', false);
    }

    /** @test */
    public function referente_con_config_completa_ve_bloque_tipos_efectivos_en_hub(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'trimestral',
            'aggregation_type' => 'ultimo_dato',
        ]));

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $this->fieldB->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'linea_base',
            'aggregation_type' => null,
        ]));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referente->id,
        ]));

        $center = $this->createCenterForSsConfigTests();

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $center->id,
            'aplica' => true,
            'seleccionado' => true,
        ]));

        $this->actingAs($this->referente)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.carga', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]))
            ->assertOk()
            ->assertSee('Configuración de carga para su Servicio de Salud', false)
            ->assertSee('Trimestral', false)
            ->assertSee('Último dato', false)
            ->assertSee('Línea base', false)
            ->assertSee('Descargar plantilla XLSX', false);
    }

    /** @test */
    public function vista_carga_no_muestra_columna_periodicidad_en_tabla_establecimientos(): void
    {
        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador semestral',
            'variable_key' => 'C',
            'componente' => 'numerador',
            'input_type' => 'semestral',
            'aggregation_type' => 'sumatoria',
            'orden' => 2,
        ]));

        foreach ([$this->fieldA, $this->fieldB] as $field) {
            MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
                'met_data_field_id' => $field->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'input_type' => $field->input_type,
                'aggregation_type' => $field->input_type === 'linea_base' ? null : 'sumatoria',
            ]));
        }

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => MetDataField::query()
                ->where('met_indicator_id', $this->indicator->id)
                ->where('variable_key', 'C')
                ->value('id'),
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'semestral',
            'aggregation_type' => 'sumatoria',
        ]));

        $center = $this->createCenterForSsConfigTests();

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $center->id,
            'aplica' => true,
            'seleccionado' => true,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.carga', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]))
            ->assertOk()
            ->assertSee('Carga manual', false)
            ->assertDontSee('>Periodicidad<', false);
    }

    /** @test */
    public function bloquea_cambio_tipo_entrada_cuando_hay_datos_cargados(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
        ]));

        $center = $this->createCenterForSsConfigTests();

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 10,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorFieldSsConfigComponent::class, [
            'indicatorId' => $this->indicator->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->set("fieldConfigs.{$this->fieldA->id}.input_type", 'linea_base')
            ->set("fieldConfigs.{$this->fieldB->id}.input_type", 'linea_base')
            ->set("fieldConfigs.{$this->fieldB->id}.aggregation_type", null)
            ->call('saveConfiguration')
            ->assertHasErrors(["fieldConfigs.{$this->fieldA->id}.input_type"]);

        $this->assertDatabaseHas('met_field_ss_configs', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'mensual',
        ]);
    }

    /** @test */
    public function permite_cambio_agregacion_con_advertencia_cuando_hay_datos_cargados(): void
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

        $center = $this->createCenterForSsConfigTests();

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 10,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorFieldSsConfigComponent::class, [
            'indicatorId' => $this->indicator->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->set("fieldConfigs.{$this->fieldA->id}.aggregation_type", 'ultimo_dato')
            ->call('saveConfiguration')
            ->assertHasNoErrors()
            ->assertSee('El tipo de agregaci', false)
            ->assertSee('cambi', false)
            ->assertSee('variables con datos ya cargados', false);

        $this->assertDatabaseHas('met_field_ss_configs', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'aggregation_type' => 'ultimo_dato',
        ]);
    }

    /** @test */
    public function panel_ss_muestra_tipo_entrada_bloqueado_cuando_hay_datos_cargados(): void
    {
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
        ]));

        $center = $this->createCenterForSsConfigTests();

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 10,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorFieldSsConfigComponent::class, [
            'indicatorId' => $this->indicator->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertSee('El tipo de entrada queda fijo porque ya hay datos cargados', false)
            ->assertSee('cambiar el tipo de agregaci', false)
            ->assertSee(' altera', false);
    }

    /** @test */
    public function panel_ss_nunca_usa_collapse(): void
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

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorFieldSsConfigComponent::class, [
            'indicatorId' => $this->indicator->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertDontSee('panel-collapse collapse', false)
            ->assertDontSee('glyphicon-chevron-right', false);
    }

    /** @test */
    public function vista_carga_usa_tabs_compass_para_control_gestion(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.carga', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]))
            ->assertOk()
            ->assertSee('nav-tabs', false)
            ->assertSee('tab-carga-datos', false)
            ->assertSee('tab-tipos-entrada', false)
            ->assertSee('Tipos de entrada y agregaci', false);
    }

    /** @test */
    public function referente_no_puede_abrir_carga_de_establecimiento_sin_config_ss(): void
    {
        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referente->id,
        ]));

        $center = $this->createCenterForSsConfigTests();

        $this->actingAs($this->referente)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.show', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
            'center' => $center,
        ]))->assertForbidden();
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

    private function createCenterForSsConfigTests(): CenCenter
    {
        return $this->createMinimalCenCenter([
            'codigo' => 'CEN-SS-01',
            'nombre_oficial' => 'Hospital SS Config',
            'nombre_corto' => 'HospSS',
        ]);
    }
}
