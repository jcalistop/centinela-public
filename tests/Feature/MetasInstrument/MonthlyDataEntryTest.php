<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\Metas\DataFieldInputType;
use App\Jobs\MetCalculateIndicatorScopeJob;
use App\Livewire\MetasInstrument\MonthlyDataEntryComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetDataField;
use App\Models\MetFieldSsConfig;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorReferente;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MonthlyDataEntryTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private User $referenteAsignado;

    private User $referenteNoAsignado;

    private MetIndicator $indicator;

    private CenCenter $center;

    private MetDataField $fieldA;

    private MetDataField $fieldB;

    private MetDataField $fieldC;

    private CenCenter $centerOtroServicio;

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

        Role::unguarded(fn () => Role::updateOrCreate(
            ['id' => 1],
            ['name' => 'admin', 'display_name' => 'Administrador']
        ));

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $this->referenteAsignado = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->referenteAsignado->role()->associate($referenteRole)->save();

        $this->referenteNoAsignado = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->referenteNoAsignado->role()->associate($referenteRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

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

        CenHieDependency::unguarded(fn () => CenHieDependency::updateOrCreate(
            ['id' => 2],
            [
                'codigo' => 'SS02',
                'nombre' => 'Servicio de Salud Externo',
                'tipo_dependencia' => 'SERVICIO',
            ]
        ));

        $this->centerOtroServicio = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-999',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 2,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Externo',
            'nombre_corto' => 'HospExt',
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
            'clave' => 'MET-01',
            'nombre' => 'Indicador mensual test',
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

        $this->fieldC = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Línea base',
            'variable_key' => 'C',
            'componente' => 'numerador',
            'input_type' => 'linea_base',
            'orden' => 2,
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

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referenteAsignado->id,
        ]));

        $this->seedCompleteFieldSsConfigs();
    }

    /** @test */
    public function referente_asignado_puede_guardar_fila_mensual(): void
    {
        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.1.{$this->fieldA->id}", '10')
            ->set("monthlyValues.1.{$this->fieldB->id}", '20')
            ->call('saveRow', 1)
            ->assertHasNoErrors()
            ->assertSet('preview', '50.00');

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 1,
        ]);

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldB->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 1,
        ]);
    }

    /** @test */
    public function referente_no_asignado_no_puede_guardar(): void
    {
        $this->actingAs($this->referenteNoAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.1.{$this->fieldA->id}", '10')
            ->call('saveRow', 1)
            ->assertHasErrors(['general']);

        $this->assertDatabaseMissing('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 1,
        ]);
    }

    /** @test */
    public function etapa_cerrado_deshabilita_edicion(): void
    {
        IndicatorTypeSetting::query()->update(['etapa_vigente' => 'CERRADO']);

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->assertSet('canEdit', false)
            ->call('saveRow', 1)
            ->assertHasErrors(['general']);
    }

    /** @test */
    public function save_baseline_row_guarda_unico_con_mes_null(): void
    {
        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("baselineValues.{$this->fieldC->id}", '100')
            ->call('saveBaselineRow')
            ->set("baselineValues.{$this->fieldC->id}", '200')
            ->call('saveBaselineRow')
            ->assertHasNoErrors();

        $rows = MetMonthlyData::query()
            ->where('met_data_field_id', $this->fieldC->id)
            ->where('cen_center_id', $this->center->id)
            ->where('year_id', $this->year->id)
            ->whereNull('mes')
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('200.0000', number_format((float) $rows->first()->valor, 4, '.', ''));
    }

    /** @test */
    public function denominador_como_linea_base_por_ss_config_guarda_correctamente(): void
    {
        // Escenario: campo originalmente 'mensual' en met_data_fields,
        // pero MetFieldSsConfig lo sobrescribe a 'linea_base'.
        // Esto causa que validateMonthConsistency() vea 'mensual' desde BD
        // con mes=null y lance ValidationException.
        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::where('met_data_field_id', $this->fieldB->id)->update([
            'input_type' => 'linea_base',
            'aggregation_type' => null,
        ]));

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("baselineValues.{$this->fieldB->id}", '50')
            ->call('saveBaselineRow')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldB->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => null,
            'valor' => 50,
        ]);
    }

    /** @test */
    public function muestra_error_divisor_cero_en_preview(): void
    {
        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.1.{$this->fieldA->id}", '10')
            ->set("monthlyValues.1.{$this->fieldB->id}", '0')
            ->call('saveRow', 1)
            ->assertSet('preview', 'Error: divisor cero');
    }

    /** @test */
    public function muestra_cero_en_preview_cuando_totales_son_cero(): void
    {
        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-RATIO-PREVIEW',
            'nombre' => 'Indicador ratio preview',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        $fieldA = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Numerador ratio',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'orden' => 0,
        ]));

        $fieldB = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Denominador ratio',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'orden' => 1,
        ]));

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $indicator->id,
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
        ]));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referenteAsignado->id,
        ]));

        foreach ([$fieldA, $fieldB] as $field) {
            MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
                'met_data_field_id' => $field->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'input_type' => 'mensual',
                'aggregation_type' => 'sumatoria',
            ]));
        }

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.12.{$fieldA->id}", '0')
            ->set("monthlyValues.12.{$fieldB->id}", '0')
            ->call('saveRow', 12)
            ->assertSet('preview', '0.00');
    }

    /** @test */
    public function valida_que_el_valor_mensual_sea_numerico(): void
    {
        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.1.{$this->fieldA->id}", 'no-numero')
            ->call('saveRow', 1)
            ->assertHasErrors(["monthlyValues.1.{$this->fieldA->id}" => 'numeric']);
    }

    /** @test */
    public function rechaza_guardar_linea_base_con_mes_distinto_de_null(): void
    {
        $this->expectException(ValidationException::class);

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        MetMonthlyData::create([
            'met_data_field_id' => $this->fieldC->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 1,
            'valor' => 100,
        ]);
    }

    /** @test */
    public function control_gestion_puede_guardar_carga_mensual_en_auto_evaluacion(): void
    {
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $controlGestion->role()->associate($cgRole)->save();

        $this->actingAs($controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->assertSet('canEdit', true)
            ->set("monthlyValues.1.{$this->fieldA->id}", '5')
            ->set("monthlyValues.1.{$this->fieldB->id}", '5')
            ->call('saveRow', 1)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 1,
        ]);
    }

    /** @test */
    public function admin_puede_guardar_carga_mensual_en_auto_evaluacion_sin_asignacion(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $admin->role()->associate($adminRole)->save();

        $this->actingAs($admin)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->assertSet('canEdit', true)
            ->set("monthlyValues.1.{$this->fieldA->id}", '8')
            ->set("monthlyValues.1.{$this->fieldB->id}", '4')
            ->call('saveRow', 1)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 1,
        ]);
    }

    /** @test */
    public function referente_ve_y_guarda_campo_semestral_en_carga(): void
    {
        $semestralField = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Dato semestral',
            'variable_key' => 'S',
            'componente' => 'numerador',
            'input_type' => 'semestral',
            'aggregation_type' => 'sumatoria',
            'orden' => 3,
        ]));

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $semestralField->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'semestral',
            'aggregation_type' => 'sumatoria',
        ]));

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->assertSee('Carga semestral (Jun y Dic)')
            ->assertSee('S')
            ->set("monthlyValues.6.{$this->fieldA->id}", '1')
            ->set("monthlyValues.6.{$this->fieldB->id}", '1')
            ->set("monthlyValues.6.{$semestralField->id}", '10')
            ->set("monthlyValues.12.{$this->fieldA->id}", '2')
            ->set("monthlyValues.12.{$this->fieldB->id}", '2')
            ->set("monthlyValues.12.{$semestralField->id}", '5')
            ->call('saveRow', 6)
            ->call('saveRow', 12)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $semestralField->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 6,
            'valor' => 10,
        ]);

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $semestralField->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 12,
            'valor' => 5,
        ]);
    }

    /** @test */
    public function referente_ve_y_guarda_campo_trimestral_en_carga(): void
    {
        $trimestralField = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Dato trimestral',
            'variable_key' => 'T',
            'componente' => 'numerador',
            'input_type' => 'trimestral',
            'aggregation_type' => 'sumatoria',
            'orden' => 4,
        ]));

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $trimestralField->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'trimestral',
            'aggregation_type' => 'sumatoria',
        ]));

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->assertSee('Carga trimestral (Mar, Jun, Sep, Dic)')
            ->assertSee('T')
            ->set("monthlyValues.3.{$this->fieldA->id}", '1')
            ->set("monthlyValues.3.{$this->fieldB->id}", '1')
            ->set("monthlyValues.3.{$trimestralField->id}", '4')
            ->set("monthlyValues.6.{$this->fieldA->id}", '1')
            ->set("monthlyValues.6.{$this->fieldB->id}", '1')
            ->set("monthlyValues.6.{$trimestralField->id}", '6')
            ->set("monthlyValues.9.{$this->fieldA->id}", '1')
            ->set("monthlyValues.9.{$this->fieldB->id}", '1')
            ->set("monthlyValues.9.{$trimestralField->id}", '8')
            ->set("monthlyValues.12.{$this->fieldA->id}", '1')
            ->set("monthlyValues.12.{$this->fieldB->id}", '1')
            ->set("monthlyValues.12.{$trimestralField->id}", '10')
            ->call('saveRow', 3)
            ->call('saveRow', 6)
            ->call('saveRow', 9)
            ->call('saveRow', 12)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $trimestralField->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 3,
            'valor' => 4,
        ]);

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $trimestralField->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 12,
            'valor' => 10,
        ]);
    }

    /** @test */
    public function referente_ve_y_guarda_campo_anual_en_carga(): void
    {
        $anualField = MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Dato anual',
            'variable_key' => 'Y',
            'componente' => 'numerador',
            'input_type' => 'anual',
            'aggregation_type' => 'ultimo_dato',
            'orden' => 5,
        ]));

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $anualField->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'anual',
            'aggregation_type' => 'ultimo_dato',
        ]));

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->assertSee('Carga anual (Dic)')
            ->assertSee('Y')
            ->set("monthlyValues.12.{$this->fieldA->id}", '1')
            ->set("monthlyValues.12.{$this->fieldB->id}", '1')
            ->set("monthlyValues.12.{$anualField->id}", '33')
            ->call('saveRow', 12)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $anualField->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 12,
            'valor' => 33,
        ]);
    }

    /** @test */
    public function establishment_detail_page_shows_indicator_title_and_hub_button(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $admin->role()->associate($adminRole)->save();

        $this->actingAs($admin)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.show', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
            'center' => $this->center,
        ]))
            ->assertOk()
            ->assertSee('Carga mensual —', false)
            ->assertSee($this->indicator->clave, false)
            ->assertSee($this->indicator->nombre, false)
            ->assertSee('Panel metas Ley 18.834', false)
            ->assertSee('btn-success', false)
            ->assertDontSee('Panel '.$this->indicatorType->nombre_indicador, false);
    }

    /** @test */
    public function establishment_detail_19664_shows_correct_hub_button(): void
    {
        $type19664 = $this->indicatorTypeByRoute('metas-19664', ['estado' => true]);

        $indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $type19664->id,
            'clave' => 'MET-664',
            'nombre' => 'Indicador 19.664',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $admin->role()->associate($adminRole)->save();

        $this->actingAs($admin)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.show', [
            'indicatorType' => $type19664,
            'indicator' => $indicator,
            'center' => $this->center,
        ]))
            ->assertOk()
            ->assertSee('Panel metas Ley 19.664', false);
    }

    /** @test */
    public function bloquea_acceso_directo_a_establecimiento_de_otro_servicio(): void
    {
        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.show', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
            'center' => $this->centerOtroServicio,
        ]))->assertForbidden();
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

        MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
            'met_data_field_id' => $this->fieldC->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'input_type' => 'linea_base',
            'aggregation_type' => null,
        ]));
    }

    /** @test */
    public function save_row_encola_job_de_recalculo_scope(): void
    {
        Bus::fake();

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.1.{$this->fieldA->id}", '10')
            ->set("monthlyValues.1.{$this->fieldB->id}", '20')
            ->call('saveRow', 1)
            ->assertHasNoErrors();

        Bus::assertDispatched(MetCalculateIndicatorScopeJob::class);
    }

    /** @test */
    public function carga_muestra_valores_enteros_sin_decimales_al_recargar(): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 3,
            'valor' => 100,
        ]));

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldB->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 3,
            'valor' => 12.34,
        ]));

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->assertSet("monthlyValues.3.{$this->fieldA->id}", '100')
            ->assertSet("monthlyValues.3.{$this->fieldB->id}", '12.34');
    }

    /** @test */
    public function referente_ve_bloque_tipos_efectivos_en_establecimiento(): void
    {
        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->assertSee('Configuración de carga para su Servicio de Salud', false)
            ->assertSee('Mensual', false)
            ->assertSee('Sumatoria', false)
            ->assertDontSee('Periodicidad:', false)
            ->assertSee('Fórmula de cálculo', false)
            ->assertSee('metas-monthly-capture-section', false);
    }

    /** @test */
    public function save_field_row_incompleto_rechaza_captura_parcial(): void
    {
        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.2.{$this->fieldA->id}", '5')
            ->call('saveRow', 2)
            ->assertHasErrors(['month.2'])
            ->assertDispatched('toast', type: 'error');

        $this->assertDatabaseMissing('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 2,
        ]);
    }

    /** @test */
    public function save_row_vacio_muestra_aviso_de_periodo_vaciado(): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 2,
            'valor' => 5,
        ]));

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.2.{$this->fieldA->id}", '')
            ->set("monthlyValues.2.{$this->fieldB->id}", '')
            ->call('saveRow', 2)
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'warning');
    }

    /** @test */
    public function save_row_vacio_periodo_incompleto_previo_elimina_datos(): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 2,
            'valor' => 5,
        ]));

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.2.{$this->fieldA->id}", '')
            ->set("monthlyValues.2.{$this->fieldB->id}", '')
            ->call('saveRow', 2)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 2,
            'valor' => null,
        ]);
    }

    /** @test */
    public function save_row_mes_completo_con_ceros_guarda_sin_error(): void
    {
        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.4.{$this->fieldA->id}", '0')
            ->set("monthlyValues.4.{$this->fieldB->id}", '0')
            ->call('saveRow', 4)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_monthly_data', [
            'met_data_field_id' => $this->fieldB->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'mes' => 4,
            'valor' => 0,
        ]);
    }

    /** @test */
    public function period_row_shows_calculation_error_on_draft_divisor_cero_before_save(): void
    {
        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.1.{$this->fieldA->id}", '1')
            ->set("monthlyValues.1.{$this->fieldB->id}", '0')
            ->assertSee('label-danger', false)
            ->assertSee(MetResult::MESSAGE_DIVISOR_CERO, false);
    }

    /** @test */
    public function period_row_shows_calculation_error_after_save_divisor_cero(): void
    {
        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.1.{$this->fieldA->id}", '1')
            ->set("monthlyValues.1.{$this->fieldB->id}", '0')
            ->call('saveRow', 1)
            ->assertHasNoErrors()
            ->assertSee('label-danger', false)
            ->assertSee(MetResult::MESSAGE_DIVISOR_CERO, false);
    }

    /** @test */
    public function period_row_shows_calculation_error_not_listo(): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 2,
            'valor' => 10,
        ]));

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldB->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 2,
            'valor' => 0,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-02',
            'numerador' => 10,
            'denominador' => 0,
            'resultado' => null,
            'cumple_meta' => false,
            'error_message' => MetResult::MESSAGE_DIVISOR_CERO,
        ]));

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->assertSee('label-danger', false)
            ->assertSee(MetResult::MESSAGE_DIVISOR_CERO, false);
    }

    /** @test */
    public function period_row_shows_persisted_incomplete_when_draft_cleared_but_bd_partial(): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 2,
            'valor' => 100,
        ]));

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.2.{$this->fieldA->id}", '')
            ->set("monthlyValues.2.{$this->fieldB->id}", '')
            ->assertSet('persistedIncompleteMonths.2', true)
            ->assertSee('Carga incompleta — complete o vacíe el mes', false);
    }

    /** @test */
    public function period_row_draft_incomplete_takes_priority_over_calculation_error(): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 2,
            'valor' => 10,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-02',
            'numerador' => 10,
            'denominador' => 0,
            'resultado' => null,
            'cumple_meta' => false,
            'error_message' => MetResult::MESSAGE_DIVISOR_CERO,
        ]));

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        $component = Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->set("monthlyValues.2.{$this->fieldA->id}", '5');

        $this->assertSame(
            'draft_incomplete',
            $component->instance()->periodRowStatus(2, DataFieldInputType::Mensual)['kind'],
        );
    }

    /** @test */
    public function period_row_ready_after_complete_save_clears_persisted_incomplete(): void
    {
        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $this->fieldA->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'mes' => 3,
            'valor' => 100,
        ]));

        $this->actingAs($this->referenteAsignado)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicator->id,
            'centerId' => $this->center->id,
        ])
            ->assertSet('persistedIncompleteMonths.3', true)
            ->set("monthlyValues.3.{$this->fieldB->id}", '50')
            ->call('saveRow', 3)
            ->assertHasNoErrors()
            ->assertSet('persistedIncompleteMonths', []);
    }

    private function createCenterCatalogDependencies(): void {}
}
