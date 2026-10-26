<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Livewire\MetasInstrument\EstablishmentResultsDetailComponent;
use App\Livewire\MetasInstrument\IndicatorCatalogComponent;
use App\Livewire\MetasInstrument\IndicatorDesempenoComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetDataField;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MetasCopyImageTest extends TestCase
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

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::firstOrCreate(
            ['cen_hie_dependency_id' => 1, 'module_key' => 'metas-18834'],
            ['enabled' => true],
        ));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::firstOrCreate(
            [
                'indicator_type_id' => $this->indicatorType->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
            ],
            [
                'etapa_vigente' => 'AUTOEVALUACION',
                'habilitado' => true,
            ],
        ));

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
            'nombre_corto' => 'Hospital Test',
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
            'clave' => 'MET-COPY-01',
            'nombre' => 'Indicador copiar imagen',
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 20,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'semestral',
            'aggregation_type' => 'sumatoria',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'linea_base',
            'aggregation_type' => null,
            'orden' => 1,
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => true,
            'ponderacion' => 100,
        ]));
    }

    /** @test */
    public function catalogo_muestra_copiar_imagen_junto_a_imprimir(): void
    {
        Livewire::test(IndicatorCatalogComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertOk()
            ->assertSee('Imprimir', false)
            ->assertSee('Copiar imagen', false)
            ->assertSee('data-metas-copy-image="#metas-capture-catalog"', false)
            ->assertSee('id="metas-capture-catalog"', false)
            ->assertSee('metas-capture-root', false)
            ->assertSee('metas-catalog-alerts no-capture', false)
            ->assertSee('metas-catalog-referentes-alert', false);
    }

    /** @test */
    public function desempeno_muestra_copiar_imagen_y_root_captura(): void
    {
        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertOk()
            ->assertSee('Imprimir', false)
            ->assertSee('Copiar imagen', false)
            ->assertSee('data-metas-copy-image="#metas-capture-desempeno"', false)
            ->assertSee('id="metas-capture-desempeno"', false);
    }

    /** @test */
    public function autoevaluacion_muestra_copiar_imagen_y_root_captura(): void
    {
        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertOk()
            ->assertSee('Imprimir', false)
            ->assertSee('Copiar imagen', false)
            ->assertSee('data-metas-copy-image="#metas-capture-autoevaluacion"', false)
            ->assertSee('id="metas-capture-autoevaluacion"', false);
    }

    /** @test */
    public function autoevaluacion_sin_datos_usa_celdas_sueltas_sin_colspan(): void
    {
        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => null,
            'denominador' => null,
            'resultado' => null,
            'cumple_meta' => false,
            'error_message' => 'N/D',
        ]));

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertOk()
            ->assertDontSee('colspan="4"', false)
            ->assertDontSee('>Sin datos<', false)
            ->assertSee('metas-results-col--formula', false)
            ->assertDontSee('Numerador', false)
            ->assertDontSee('Denominador', false)
            ->assertSeeHtml('class="text-center">—</td>');
    }

    /** @test */
    public function autoevaluacion_marca_columnas_anchas_como_no_capture(): void
    {
        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 10,
            'denominador' => 100,
            'resultado' => 10,
            'cumple_meta' => false,
        ]));

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertOk()
            ->assertSee('metas-results-col--formula', false)
            ->assertSee('Fórmula de cálculo', false)
            ->assertSee('10 / 100', false)
            ->assertSee('colspan="9" class="text-right no-capture"', false)
            ->assertSee('colspan="9" class="text-right capture-only"', false);
    }

    /** @test */
    public function autoevaluacion_meta_nacional_referencia_usa_texto_en_captura_no_icono(): void
    {
        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update(['meta_efectiva' => 90.54]);

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 10,
            'denominador' => 100,
            'resultado' => 10,
            'cumple_meta' => false,
        ]));

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertOk()
            ->assertSee('voyager-info-circled text-muted no-capture', false)
            ->assertSee('Meta nacional (referencia):', false)
            ->assertDontSee('>Nac.:', false);
    }

    /** @test */
    public function autoevaluacion_formula_compleja_mantiene_etiqueta_formula_compleja(): void
    {
        MetDataField::query()->where('met_indicator_id', $this->indicator->id)->delete();

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $this->indicator->id,
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
            ],
            'description' => '(A - B) / B * 100',
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

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => null,
            'denominador' => null,
            'resultado' => 42.5,
            'cumple_meta' => true,
        ]));

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertOk()
            ->assertSee('Fórmula compleja', false)
            ->assertDontSee('(A - B) / B * 100', false)
            ->assertDontSee('metas-results-formula-expression', false);
    }

    /** @test */
    public function autoevaluacion_formula_simple_omite_decimales_cero(): void
    {
        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 12.5,
            'denominador' => 100.0,
            'resultado' => 12.5,
            'cumple_meta' => false,
        ]));

        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertOk()
            ->assertSee('12.5 / 100', false);
    }

    /** @test */
    public function estilos_captura_y_script_vite_en_paginas_metas(): void
    {
        $this->get(route('metas.instrument.index', $this->indicatorType))
            ->assertOk()
            ->assertSee('body.metas-capturing', false)
            ->assertSee('Copiar imagen', false);

        $this->get(route('metas.instrument.indicators.show', [$this->indicatorType, $this->indicator]))
            ->assertOk()
            ->assertSee('Copiar imagen', false);

        $this->get(route('metas.instrument.establishment.results', [
            'indicatorType' => $this->indicatorType,
            'center' => $this->center,
        ]))
            ->assertOk()
            ->assertSee('Copiar imagen', false)
            ->assertSee('Imprimir', false)
            ->assertSee('.no-capture', false)
            ->assertSee('td.capture-only', false);
    }

    private function createCenterCatalogDependencies(): void {}
}
