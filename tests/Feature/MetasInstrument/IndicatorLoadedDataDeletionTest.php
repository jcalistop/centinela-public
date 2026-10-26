<?php

namespace Tests\Feature\MetasInstrument;

use App\Jobs\MetCalculateResultsJob;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetDataField;
use App\Models\MetFieldSsConfig;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetIndicatorReferente;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetIndicatorCatalogPersistenceService;
use App\Services\Metas\MetIndicatorLoadedDataDeletionService;
use App\Services\MetCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class IndicatorLoadedDataDeletionTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private IndicatorTypeSetting $setting;

    private User $referente;

    private User $controlGestion;

    private CenCenter $center;

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

        CenHieDependency::unguarded(fn () => CenHieDependency::updateOrCreate(
            ['id' => 2],
            ['codigo' => 'SS02', 'nombre' => 'Servicio Externo', 'tipo_dependencia' => 'SERVICIO']
        ));

        Role::unguarded(fn () => Role::updateOrCreate(
            ['id' => 1],
            ['name' => 'admin', 'display_name' => 'Administrador']
        ));

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control de Gestión']);

        $this->referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->referente->role()->associate($referenteRole)->save();

        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->setting = IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        $this->center = $this->createMinimalCenCenter([
            'codigo' => 'CEN-001',
            'nombre_oficial' => 'Hospital Test',
            'nombre_corto' => 'HospTest',
        ]);

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-01',
            'nombre' => 'Indicador test',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $this->year->id,
            'orden' => 1,
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

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referente->id,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'agrupa_ss' => false,
            'seleccionado' => false,
        ]));

        $this->seedCompleteFieldSsConfigs();
    }

    /** @test */
    public function control_gestion_puede_eliminar_datos_cargados_del_indicador(): void
    {
        Bus::fake();

        $this->seedLoadedDataForSs(1);

        $response = $this->actingAs($this->controlGestion)
            ->withSession(['indicator_year' => $this->year])
            ->delete(route('metas.instrument.indicador.datos.destroy', [
                'indicatorType' => $this->indicatorType,
                'indicator' => $this->indicator,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('alert-type', 'success');

        $this->assertDatabaseMissing('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
        ]);

        $this->assertDatabaseMissing('met_results', [
            'met_indicator_id' => $this->indicator->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'etapa' => MetCalculationService::ETAPA_AUTOEVALUACION,
        ]);

        Bus::assertDispatched(MetCalculateResultsJob::class);
    }

    /** @test */
    public function referente_no_puede_eliminar_datos_cargados(): void
    {
        $this->seedLoadedDataForSs(1);

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->delete(route('metas.instrument.indicador.datos.destroy', [
                'indicatorType' => $this->indicatorType,
                'indicator' => $this->indicator,
            ]))
            ->assertForbidden();

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
        ]);
    }

    /** @test */
    public function eliminacion_rechazada_fuera_de_etapa_autoevaluacion(): void
    {
        $this->setting->update(['etapa_vigente' => 'PRELIMINAR']);
        $this->seedLoadedDataForSs(1);

        $this->actingAs($this->controlGestion)
            ->withSession(['indicator_year' => $this->year])
            ->delete(route('metas.instrument.indicador.datos.destroy', [
                'indicatorType' => $this->indicatorType,
                'indicator' => $this->indicator,
            ]))
            ->assertRedirect()
            ->assertSessionHas('alert-type', 'error');

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
        ]);
    }

    /** @test */
    public function eliminacion_no_afecta_datos_de_otro_indicador(): void
    {
        Bus::fake();

        $otroIndicador = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-02',
            'nombre' => 'Otro indicador',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $this->year->id,
            'orden' => 2,
            'estado' => true,
        ]));

        $otroCampo = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $otroIndicador->id,
            'label' => 'Numerador otro',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'orden' => 0,
        ]));

        $this->seedLoadedDataForSs(1);

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $otroCampo->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 4,
            'valor' => 99,
        ]));

        $this->actingAs($this->controlGestion)
            ->withSession(['indicator_year' => $this->year])
            ->delete(route('metas.instrument.indicador.datos.destroy', [
                'indicatorType' => $this->indicatorType,
                'indicator' => $this->indicator,
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
        ]);

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $otroCampo->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'valor' => 99,
        ]);
    }

    /** @test */
    public function vista_carga_muestra_boton_eliminar_datos_solo_para_control_gestion(): void
    {
        $this->seedLoadedDataForSs(1);

        $this->actingAs($this->controlGestion)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.carga', [
                'indicatorType' => $this->indicatorType,
                'indicator' => $this->indicator,
            ]))
            ->assertOk()
            ->assertSee('Eliminar datos')
            ->assertSee('Volver al indicador', false)
            ->assertSee(
                route('metas.instrument.indicators.show', [$this->indicatorType, $this->indicator]).'#tab-desempeno',
                false
            )
            ->assertDontSee('Volver al Catálogo de Indicadores', false);

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year])
            ->get(route('metas.instrument.indicador.carga', [
                'indicatorType' => $this->indicatorType,
                'indicator' => $this->indicator,
            ]))
            ->assertOk()
            ->assertDontSee('Eliminar datos');
    }

    /** @test */
    public function guard_any_dependency_ve_datos_de_otro_ss(): void
    {
        $this->seedLoadedDataForSs(2);

        $this->actingAs($this->controlGestion);

        $this->assertTrue(app(MetIndicatorLoadedDataDeletionService::class)->hasLoadedDataInAnyDependency(
            $this->indicator,
            (int) $this->year->id
        ));
    }

    /** @test */
    public function field_has_monthly_data_ve_datos_de_otro_ss(): void
    {
        $this->seedLoadedDataForSs(2);

        $this->actingAs($this->controlGestion);

        $this->assertTrue(app(MetIndicatorCatalogPersistenceService::class)->fieldHasMonthlyData(
            (int) $this->fieldA->id
        ));
    }

    private function seedLoadedDataForSs(int $dependencyId): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => $dependencyId,
            'year_id' => $this->year->id,
            'mes' => 3,
            'valor' => 12,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => $dependencyId,
            'year_id' => $this->year->id,
            'periodo' => 'MES-03',
            'numerador' => 12,
            'denominador' => 24,
            'resultado' => 50,
            'etapa' => MetCalculationService::ETAPA_AUTOEVALUACION,
        ]));
    }

    private function seedCompleteFieldSsConfigs(): void
    {
        foreach ([$this->fieldA, $this->fieldB] as $field) {
            MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
                'met_data_field_id' => $field->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'input_type' => 'mensual',
                'aggregation_type' => 'sumatoria',
            ]));
        }
    }
}
