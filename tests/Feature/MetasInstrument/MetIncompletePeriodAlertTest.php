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
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Metas\MetIndicatorCalculationErrorService;
use App\Services\Metas\MetIndicatorIncompletePeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MetIncompletePeriodAlertTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private User $controlGestion;

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
        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();

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
            'clave' => 'MET-18834-2026-01',
            'nombre' => 'Indicador incompleto test',
            'estado' => true,
            'orden' => 1,
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

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $this->indicator->id,
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
            ],
            'description' => 'A / B',
        ]));

        $this->seedCompleteSsConfigForIndicator();
    }

    /** @test */
    public function catalogo_muestra_alerta_carga_incompleta(): void
    {
        $this->seedIncompletePeriod();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorCatalogComponent::class)
            ->assertSee('metas-catalog-alerts', false)
            ->assertSee('metas-incomplete-period-alert', false)
            ->assertSee('Carga incompleta', false);
    }

    /** @test */
    public function ficha_muestra_banner_carga_incompleta(): void
    {
        $this->seedIncompletePeriod();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicators.show', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]))
            ->assertOk()
            ->assertSee('metas-incomplete-period-alert', false)
            ->assertSee('Carga incompleta', false);
    }

    /** @test */
    public function hub_carga_muestra_alerta_por_establecimiento(): void
    {
        $this->seedIncompletePeriod();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.carga', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ]))
            ->assertOk()
            ->assertSee('metas-incomplete-period-alert', false)
            ->assertSee('Carga incompleta', false);
    }

    /** @test */
    public function periodo_incompleto_no_dispara_alerta_error_calculo(): void
    {
        $center = $this->seedIncompletePeriodWithNdResult();

        $calculationService = app(MetIndicatorCalculationErrorService::class);
        $incompleteService = app(MetIndicatorIncompletePeriodService::class);

        $this->assertFalse($calculationService->hasErrorsForIndicator(
            (int) $this->indicator->id,
            1,
            $this->year->id,
        ));
        $this->assertTrue($incompleteService->hasIncompletePeriodsForIndicator(
            (int) $this->indicator->id,
            1,
            $this->year->id,
        ));
    }

    private function seedIncompletePeriod(): CenCenter
    {
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
            'mes' => 2,
            'valor' => 4,
        ]));

        return $center;
    }

    private function seedIncompletePeriodWithNdResult(): CenCenter
    {
        $center = $this->seedIncompletePeriod();

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-02',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => null,
            'denominador' => null,
            'resultado' => null,
            'error_message' => 'N/D',
        ]));

        return $center;
    }

    private function seedCompleteSsConfigForIndicator(): void
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

    private function createCenterForTests(): CenCenter
    {
        return $this->createMinimalCenCenter([
            'codigo' => 'CEN-131-01',
            'nombre_oficial' => 'Hospital 131 Test',
            'nombre_corto' => 'Hosp131',
        ]);
    }
}
