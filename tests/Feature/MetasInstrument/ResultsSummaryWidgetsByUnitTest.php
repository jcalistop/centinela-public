<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Livewire\MetasInstrument\EstablishmentResultsDetailComponent;
use App\Livewire\MetasInstrument\ResultsSummaryComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetEstablishmentResult;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMeasurementUnit;
use App\Models\MetResult;
use App\Models\MetUnitType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\MetCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResultsSummaryWidgetsByUnitTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $user;

    private IndicatorType $indicatorType20707;

    private IndicatorType $indicatorType18834;

    private CenCenter $center;

    private MetMeasurementUnit $unitA;

    private MetMeasurementUnit $unitB;

    private MetIndicator $indicator;

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

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $this->user = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->user->role()->associate($adminRole)->save();

        $this->actingAs($this->user)->withSession(['indicator_year' => $this->year]);

        $this->indicatorType20707 = $this->indicatorTypeByRoute('metas-20707', ['estado' => true]);
        $this->indicatorType18834 = $this->indicatorTypeByRoute('metas-18834', ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType20707->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        $this->createCenterCatalogDependencies();

        $this->center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-20707',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital 20707',
            'nombre_corto' => 'Hospital 20707',
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

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'indicator_type_id' => $this->indicatorType20707->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType20707->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'resultado_global' => 75.0,
            'n_seleccionados' => 2,
            'n_calculados' => 2,
        ]));

        $unitType = MetUnitType::unguarded(fn () => MetUnitType::query()->firstOrCreate(
            ['codigo' => 'URG'],
            ['nombre' => 'Urgencia', 'orden' => 1],
        ));

        $this->unitA = MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'U-A',
            'nombre' => 'Unidad A',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->unitB = MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'U-B',
            'nombre' => 'Unidad B',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType20707->id,
            'clave' => 'L707-RU',
            'nombre' => 'Indicador resultados unidad',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 50,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        foreach ([$this->unitA, $this->unitB] as $unit) {
            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $this->indicator->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
                'cen_center_id' => $this->center->id,
                'met_measurement_unit_id' => $unit->id,
                'seleccionado' => true,
                'ponderacion' => 100,
                'aplica' => true,
                'meta_efectiva' => 50,
            ]));
        }

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'met_measurement_unit_id' => $this->unitA->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 40,
            'denominador' => 100,
            'resultado' => 40,
            'cumple_meta' => false,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'met_measurement_unit_id' => $this->unitB->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 50,
            'denominador' => 100,
            'resultado' => 50,
            'cumple_meta' => true,
        ]));
    }

    /** @test */
    public function internal_unit_summary_renders_center_sections_and_unit_widgets(): void
    {
        Livewire::test(ResultsSummaryComponent::class, [
            'indicatorTypeId' => $this->indicatorType20707->id,
        ])
            ->assertSee('metas-results-center-section', false)
            ->assertSee('Hospital 20707')
            ->assertSee('U-A')
            ->assertSee('U-B')
            ->assertSee('75.00%')
            ->assertSeeHtml('metas-instrument-result-card--unit')
            ->assertSeeHtml('unidad='.$this->unitA->id)
            ->assertSeeHtml('unidad='.$this->unitB->id)
            ->assertDontSeeHtml('metas-instrument-result-card"');
    }

    /** @test */
    public function establishment_instrument_keeps_flat_results_grid(): void
    {
        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType18834->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'resultado_global' => 80.0,
            'n_seleccionados' => 1,
            'n_calculados' => 1,
        ]));

        Livewire::test(ResultsSummaryComponent::class, [
            'indicatorTypeId' => $this->indicatorType18834->id,
        ])
            ->assertSee('Hospital 20707')
            ->assertSeeHtml('class="metas-instrument-result-card"')
            ->assertDontSee('metas-results-center-section', false)
            ->assertDontSeeHtml('metas-instrument-result-card--unit');
    }

    /** @test */
    public function center_without_active_units_shows_info_message(): void
    {
        MetMeasurementUnit::query()->update(['activo' => false]);

        Livewire::test(ResultsSummaryComponent::class, [
            'indicatorTypeId' => $this->indicatorType20707->id,
        ])
            ->assertSee('Hospital 20707')
            ->assertSee('Sin unidades activas registradas')
            ->assertDontSeeHtml('metas-instrument-result-card--unit');
    }

    /** @test */
    public function detail_with_unit_query_shows_unit_specific_results(): void
    {
        Livewire::withQueryParams(['unidad' => $this->unitA->id])
            ->test(EstablishmentResultsDetailComponent::class, [
                'indicatorTypeId' => $this->indicatorType20707->id,
                'centerId' => $this->center->id,
            ])
            ->set('soloComprometidos', false)
            ->assertSet('measurementUnitId', $this->unitA->id)
            ->assertSee('Indicador resultados unidad')
            ->assertSee('40.00')
            ->assertViewHas('footerCumplimientoGlobal', 80.0);
    }

    /** @test */
    public function detail_without_unit_on_internal_unit_shows_averaged_banner(): void
    {
        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType20707->id,
            'centerId' => $this->center->id,
        ])
            ->set('soloComprometidos', false)
            ->assertSee('promedio de todas las unidades')
            ->assertSee('Indicador resultados unidad');
    }

    /** @test */
    public function invalid_unit_query_returns_not_found(): void
    {
        $this->get(route('metas.instrument.establishment.results', [
            'indicatorType' => $this->indicatorType20707,
            'center' => $this->center->id,
            'unidad' => 99999,
        ]))->assertNotFound();
    }

    /** @test */
    public function sum_weighted_compliance_for_unit_returns_unit_scope(): void
    {
        $service = app(MetCalculationService::class);

        $unitA = $service->sumWeightedComplianceForUnit(
            $this->center->id,
            $this->year->id,
            1,
            'ANUAL',
            $this->indicatorType20707->id,
            $this->unitA->id,
        );

        $unitB = $service->sumWeightedComplianceForUnit(
            $this->center->id,
            $this->year->id,
            1,
            'ANUAL',
            $this->indicatorType20707->id,
            $this->unitB->id,
        );

        $this->assertSame('80.0000', number_format((float) $unitA['resultado_global'], 4, '.', ''));
        $this->assertSame('100.0000', number_format((float) $unitB['resultado_global'], 4, '.', ''));
    }

    private function createCenterCatalogDependencies(): void {}
}
