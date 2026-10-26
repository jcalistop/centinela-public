<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Livewire\MetasInstrument\MonthlyDataEntryComponent;
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
use App\Models\MetMeasurementUnit;
use App\Models\MetResult;
use App\Models\MetUnitType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlyDataEntryUnitUxTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $admin;

    private IndicatorType $indicatorType20707;

    private IndicatorType $indicatorType18834;

    private CenCenter $center;

    private MetMeasurementUnit $unit;

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

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->admin->role()->associate($adminRole)->save();

        $this->actingAs($this->admin)->withSession(['indicator_year' => $this->year]);

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
            'codigo' => 'CEN-UX158',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital UX158',
            'nombre_corto' => 'H UX158',
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

        $unitType = MetUnitType::unguarded(fn () => MetUnitType::query()->firstOrCreate(
            ['codigo' => 'URG'],
            ['nombre' => 'Urgencia', 'orden' => 1],
        ));

        $this->unit = MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'U-UX',
            'nombre' => 'Unidad UX',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType20707->id,
            'clave' => 'L707-UX',
            'nombre' => 'Indicador UX unidad',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 50,
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
            'met_measurement_unit_id' => $this->unit->id,
            'seleccionado' => true,
            'ponderacion' => 100,
            'aplica' => true,
            'meta_efectiva' => 50,
        ]));

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
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
        ]));
    }

    /** @test */
    public function center_units_page_shows_indicator_in_title_and_panel_button(): void
    {
        $url = route('metas.instrument.indicador.center.units', [
            'indicatorType' => $this->indicatorType20707,
            'indicator' => $this->indicator,
            'center' => $this->center,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Carga mensual por unidad', false)
            ->assertSee($this->indicator->clave, false)
            ->assertSee($this->indicator->nombre, false)
            ->assertSee('Panel metas Ley 20.707', false)
            ->assertSee('btn-success', false)
            ->assertDontSee('Panel '.$this->indicatorType20707->nombre_indicador, false);
    }

    /** @test */
    public function show_unit_page_shows_indicator_in_title_and_panel_button(): void
    {
        $url = route('metas.instrument.indicador.showUnit', [
            'indicatorType' => $this->indicatorType20707,
            'indicator' => $this->indicator,
            'center' => $this->center,
            'measurementUnit' => $this->unit,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Carga mensual', false)
            ->assertSee($this->indicator->clave, false)
            ->assertSee($this->indicator->nombre, false)
            ->assertSee('Panel metas Ley 20.707', false)
            ->assertSee('btn-success', false);
    }

    /** @test */
    public function monthly_data_entry_unit_mode_hides_periodicity_and_shows_formula_section(): void
    {
        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
            'indicatorTypeId' => $this->indicatorType20707->id,
            'measurementUnitId' => $this->unit->id,
        ])
            ->assertDontSee('Periodicidad:', false)
            ->assertDontSee('class="metas-monthly-context__title"', false)
            ->assertSee('metas-monthly-context__badges--solo', false)
            ->assertSee('Configuración de carga para su Servicio de Salud', false)
            ->assertSee('Fórmula de cálculo', false)
            ->assertSee('(A / B) * 100', false)
            ->assertSee('metas-monthly-capture-section', false)
            ->assertSee('Cómo guardar:', false);
    }

    /** @test */
    public function unit_mode_period_row_shows_calculation_error_for_divisor_cero(): void
    {
        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
            'indicatorTypeId' => $this->indicatorType20707->id,
            'measurementUnitId' => $this->unit->id,
        ])
            ->set("monthlyValues.1.{$this->fieldA->id}", '1')
            ->set("monthlyValues.1.{$this->fieldB->id}", '0')
            ->assertSee('label-danger', false)
            ->assertSee(MetResult::MESSAGE_DIVISOR_CERO, false);
    }

    /** @test */
    public function monthly_data_entry_establishment_mode_uses_compass_capture_layout(): void
    {
        $indicator18834 = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType18834->id,
            'clave' => 'L188-UX',
            'nombre' => 'Indicador establecimiento',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 50,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator18834->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'orden' => 0,
        ]));

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $indicator18834->id,
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
            ],
            'description' => '(A / B) * 100',
        ]));

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $indicator18834->id,
            'centerId' => $this->center->id,
            'indicatorTypeId' => $this->indicatorType18834->id,
        ])
            ->assertDontSee('class="metas-monthly-context__title"', false)
            ->assertDontSee('Periodicidad:', false)
            ->assertSee('Fórmula de cálculo', false)
            ->assertSee('metas-monthly-capture-section', false);
    }

    private function createCenterCatalogDependencies(): void {}
}
