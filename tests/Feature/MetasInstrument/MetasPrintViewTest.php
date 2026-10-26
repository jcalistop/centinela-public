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

class MetasPrintViewTest extends TestCase
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
            'clave' => 'MET-PRINT-01',
            'nombre' => 'Indicador vista impresión',
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
    public function catalogo_muestra_boton_imprimir_y_marcadores_print(): void
    {
        Livewire::test(IndicatorCatalogComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertOk()
            ->assertSee('Imprimir', false)
            ->assertSee('metas-print-button', false)
            ->assertSee('window.print()', false)
            ->assertSee('Catálogo', false)
            ->assertSee('metas-print-header print-only', false)
            ->assertSee('metas-catalog-col--acciones no-print', false);
    }

    /** @test */
    public function catalogo_vacio_sigue_mostrando_imprimir(): void
    {
        $this->indicator->delete();

        Livewire::test(IndicatorCatalogComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertOk()
            ->assertSee('Imprimir', false)
            ->assertSee('No hay indicadores registrados para el año activo.', false);
    }

    /** @test */
    public function desempeno_muestra_boton_imprimir_y_cabecera_print(): void
    {
        Livewire::test(IndicatorDesempenoComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertOk()
            ->assertSee('Imprimir', false)
            ->assertSee('metas-print-button', false)
            ->assertSee('window.print()', false)
            ->assertSee('Desempeño — 2026', false)
            ->assertSeeHtml('class="text-center no-print"');
    }

    /** @test */
    public function autoevaluacion_muestra_boton_imprimir_y_cabecera_print_con_tabla_vacia(): void
    {
        Livewire::test(EstablishmentResultsDetailComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertOk()
            ->assertSee('Imprimir', false)
            ->assertSee('metas-print-button', false)
            ->assertSee('window.print()', false)
            ->assertSee('Autoevaluación', false)
            ->assertSee('Hospital Test', false)
            ->assertSee('Año 2026', false)
            ->assertSee('Etapa Autoevaluación', false)
            ->assertSee('No hay resultados de indicadores para este establecimiento.', false);
    }

    /** @test */
    public function autoevaluacion_oculta_columna_datos_en_marcadores_print(): void
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
            ->assertSee('Imprimir', false)
            ->assertSee('Ponderación', false)
            ->assertSee('Cumplimiento ponderado', false)
            ->assertSee('metas-results-col--ver-datos no-print', false);
    }

    /** @test */
    public function paginas_metas_incluyen_estilos_print_compartidos(): void
    {
        $this->get(route('metas.instrument.index', $this->indicatorType))
            ->assertOk()
            ->assertSee('@media print', false)
            ->assertSee('.no-print', false)
            ->assertSee('mismo look que label-default', false)
            ->assertSee('color: #333 !important', false)
            ->assertSee('border: 1px solid #555 !important', false);

        $this->get(route('metas.instrument.indicators.show', [$this->indicatorType, $this->indicator]))
            ->assertOk()
            ->assertSee('@media print', false);

        $this->get(route('metas.instrument.establishment.results', [
            'indicatorType' => $this->indicatorType,
            'center' => $this->center,
        ]))
            ->assertOk()
            ->assertSee('@media print', false)
            ->assertSee('Imprimir', false);
    }

    private function createCenterCatalogDependencies(): void {}
}
