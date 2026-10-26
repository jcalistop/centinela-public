<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Livewire\MetasInstrument\EstablishmentResultsDetailComponent;
use App\Livewire\MetasInstrument\IndicatorDesempenoComponent;
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
use App\Services\Metas\MetComplexFormulaVariablesModalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ComplexFormulaVariablesModalTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $user;

    private IndicatorType $indicatorType;

    private CenCenter $center;

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

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->createCenterCatalogDependencies();

        $this->center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-CFV-01',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Variables',
            'nombre_corto' => 'Hospital Variables',
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
            'clave' => 'MET-CFV-01',
            'nombre' => 'Indicador fórmula compleja variables',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 5,
            'meta_operador' => '>=',
            'unidad_medida' => '%',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

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
            'description' => '(A - B) / B * 100',
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Altas totales',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Altas evitables',
            'variable_key' => 'B',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => true,
            'ponderacion' => 10,
        ]));

        $fields = MetDataField::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->get()
            ->keyBy('variable_key');

        foreach (['A' => 1240, 'B' => 1215] as $variableKey => $valor) {
            MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
                'met_data_field_id' => $fields[$variableKey]->id,
                'cen_center_id' => $this->center->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'mes' => 12,
                'valor' => $valor,
            ]));
        }

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-12',
            'numerador' => null,
            'denominador' => null,
            'resultado' => 2.06,
            'cumple_meta' => false,
            'etapa' => 'AUTOEVALUACION',
        ]));
    }

    /** @test */
    public function desempeno_abre_modal_on_demand_con_variables_y_expresion(): void
    {
        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSet('showComplexFormulaVariablesModal', false)
            ->assertSee('Fórmula compleja', false)
            ->call(
                'openComplexFormulaVariablesModal',
                $this->indicator->id,
                $this->center->id,
                'MES-12',
                true,
            )
            ->assertSet('showComplexFormulaVariablesModal', true)
            ->assertSee('Variables de cálculo', false)
            ->assertSee('(A - B) / B * 100', false)
            ->assertSee('Altas totales', false)
            ->assertSee('Altas evitables', false)
            ->assertSee('1240', false)
            ->assertSee('1215', false)
            ->assertSee('2.06', false);
    }

    /** @test */
    public function autoevaluacion_abre_modal_con_periodo_anual_sintetico_y_muestra_valores(): void
    {
        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call(
                'openComplexFormulaVariablesModal',
                $this->indicator->id,
                $this->center->id,
                'ANUAL',
                true,
            )
            ->assertSet('showComplexFormulaVariablesModal', true)
            ->assertSee('Variables de cálculo', false)
            ->assertSee('1240', false)
            ->assertSee('1215', false);
    }

    /** @test */
    public function autoevaluacion_abre_modal_acotado_al_periodo(): void
    {
        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertSet('showComplexFormulaVariablesModal', false)
            ->assertSee('Fórmula compleja', false)
            ->call(
                'openComplexFormulaVariablesModal',
                $this->indicator->id,
                $this->center->id,
                'MES-12',
            )
            ->assertSet('showComplexFormulaVariablesModal', true)
            ->assertSee('Variables de cálculo', false)
            ->assertSee('Dic', false)
            ->assertSee('1240', false)
            ->assertSee('1215', false);
    }

    /** @test */
    public function renderizar_desempeno_sin_abrir_modal_no_consulta_payload_de_variables(): void
    {
        $this->mock(MetComplexFormulaVariablesModalService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('payload')->never();
        });

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSet('showComplexFormulaVariablesModal', false)
            ->assertSee('Fórmula compleja', false);
    }

    /** @test */
    public function renderizar_autoevaluacion_sin_abrir_modal_no_consulta_payload_de_variables(): void
    {
        $this->mock(MetComplexFormulaVariablesModalService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('payload')->never();
        });

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertSet('showComplexFormulaVariablesModal', false);
    }

    /** @test */
    public function usuario_de_otro_ss_recibe_403_al_abrir_modal(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS02',
            'nombre' => 'Otro Servicio',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $otherRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $otherUser = User::factory()->create([
            'role_id' => $otherRole->id,
            'cen_hie_dependency_id' => 2,
        ]);
        $otherUser->role()->associate($otherRole)->save();

        $this->actingAs($otherUser)->withSession(['indicator_year' => $this->year]);

        $this->expectException(HttpException::class);

        app(MetComplexFormulaVariablesModalService::class)->payload(
            $otherUser,
            $this->indicatorType->id,
            $this->indicator->id,
            $this->center->id,
            $this->year->id,
            'MES-12',
        );
    }

    /** @test */
    public function indicador_simple_no_muestra_trigger_de_modal_variables(): void
    {
        MetDataField::query()->where('met_indicator_id', $this->indicator->id)->delete();
        MetFormula::query()->where('met_indicator_id', $this->indicator->id)->delete();

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'aggregation_type' => 'sumatoria',
            'orden' => 1,
        ]));

        MetResult::query()->where('met_indicator_id', $this->indicator->id)->delete();
        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'MES-12',
            'numerador' => 10,
            'denominador' => 100,
            'resultado' => 10,
            'cumple_meta' => true,
            'etapa' => 'AUTOEVALUACION',
        ]));

        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertDontSee('metas-complex-formula-variables-trigger', false)
            ->assertSee('10 / 100', false);
    }

    private function createCenterCatalogDependencies(): void {}
}
