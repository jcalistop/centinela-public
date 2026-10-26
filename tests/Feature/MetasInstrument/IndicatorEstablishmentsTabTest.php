<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Enums\Metas\UnidadMedida;
use App\Livewire\MetasInstrument\IndicatorEstablishmentsComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterAdosado;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndicatorEstablishmentsTabTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $adminUser;

    private IndicatorType $indicatorType;

    private MetIndicator $indicator;

    private CenCenter $centerMadre;

    private CenCenter $centerAdosado;

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
        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $this->adminUser->role()->associate($adminRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);
        $this->enableMetasModuleAccessForDependency(1);

        $this->createCenterCatalogDependencies();

        $this->centerMadre = $this->createCenter('CEN-MADRE', 'Hospital Madre');
        $this->centerAdosado = $this->createCenter('CEN-ADOS', 'CECOSF Adosado');

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'TAB-EST-01',
            'nombre' => 'Indicador tab establecimientos',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
            'meta_valor' => 85.50,
            'meta_operador' => '>=',
            'umbral_inferior' => 0,
            'umbral_superior' => 100,
            'unidad_medida' => UnidadMedida::Porcentaje->value,
            'nivel_aplicacion' => 'Ámbito de prueba',
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->centerMadre->id,
            'aplica' => true,
            'seleccionado' => true,
            'agrupa_ss' => false,
        ]));
    }

    /** @test */
    public function show_page_includes_aplicables_convenidos_tab_after_ficha(): void
    {
        $this->actingAs($this->adminUser)->withSession(['indicator_year' => $this->year]);

        $content = $this->get(route('metas.instrument.indicators.show', [$this->indicatorType, $this->indicator]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ficha del indicador', $content);
        $this->assertStringContainsString('Establecimientos', $content);
        $this->assertStringContainsString('id="tab-aplicables-convenidos"', $content);
        $this->assertStringContainsString('Desempeño', $content);

        $fichaPos = strpos($content, 'id="tab-informacion"');
        $aplicablesPos = strpos($content, 'id="tab-aplicables-convenidos"');
        $desempenoPos = strpos($content, 'id="tab-desempeno"');

        $this->assertNotFalse($fichaPos);
        $this->assertNotFalse($aplicablesPos);
        $this->assertNotFalse($desempenoPos);
        $this->assertLessThan($aplicablesPos, $fichaPos);
        $this->assertLessThan($desempenoPos, $aplicablesPos);
    }

    /** @test */
    public function establishments_component_lists_configured_centers(): void
    {
        $this->actingAs($this->adminUser)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorEstablishmentsComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSee('Hospital Madre')
            ->assertSee('CEN-MADRE')
            ->assertSee('label-success')
            ->assertSee('label-primary')
            ->assertSee('Significado de las columnas')
            ->assertSee('Aplica:')
            ->assertSee('En convenio:')
            ->assertSee('Agrupa SS:')
            ->assertSee('nombre adosado')
            ->assertSee('metas-center-adosado-label', false)
            ->assertSee('Los establecimientos participantes son configurados por Control de Gestión.');
    }

    /** @test */
    public function establishments_component_shows_adosado_labels_under_center_name(): void
    {
        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->centerMadre->id,
            'cen_center_adosado_id' => $this->centerAdosado->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
        ]));

        $this->actingAs($this->adminUser)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorEstablishmentsComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicator,
        ])
            ->assertSee('metas-center-adosado-label', false)
            ->assertSee('CECOSF Adosado');
    }

    /** @test */
    public function internal_unit_instrument_does_not_render_adosado_labels(): void
    {
        $internalType = $this->indicatorTypeByRoute('metas-20707', ['estado' => true]);

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::firstOrCreate(
            ['cen_hie_dependency_id' => 1, 'module_key' => 'metas-20707'],
            ['enabled' => true],
        ));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::firstOrCreate(
            [
                'indicator_type_id' => $internalType->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
            ],
            [
                'etapa_vigente' => 'AUTOEVALUACION',
                'habilitado' => true,
            ],
        ));

        $internalIndicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $internalType->id,
            'clave' => 'TAB-INT-01',
            'nombre' => 'Indicador unidades internas',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
            'meta_valor' => 85.50,
            'meta_operador' => '>=',
            'umbral_inferior' => 0,
            'umbral_superior' => 100,
            'unidad_medida' => UnidadMedida::Porcentaje->value,
            'nivel_aplicacion' => 'Ámbito de prueba',
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $internalIndicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->centerMadre->id,
            'aplica' => true,
            'seleccionado' => true,
        ]));

        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->centerMadre->id,
            'cen_center_adosado_id' => $this->centerAdosado->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $internalType->id,
        ]));

        $this->actingAs($this->adminUser)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorEstablishmentsComponent::class, [
            'indicatorType' => $internalType,
            'indicator' => $internalIndicator,
        ])
            ->assertSee('Hospital Madre')
            ->assertDontSee('metas-center-adosado-label', false)
            ->assertDontSee('nombre adosado');
    }

    /** @test */
    public function establishments_component_shows_empty_message_without_configurations(): void
    {
        $indicatorSinConfig = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'TAB-EMPTY',
            'nombre' => 'Sin configuraciones',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
            'meta_valor' => 85.50,
            'meta_operador' => '>=',
            'umbral_inferior' => 0,
            'umbral_superior' => 100,
            'unidad_medida' => UnidadMedida::Porcentaje->value,
            'nivel_aplicacion' => 'Ámbito de prueba',
        ]));

        $this->actingAs($this->adminUser)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorEstablishmentsComponent::class, [
            'indicatorType' => $this->indicatorType,
            'indicator' => $indicatorSinConfig,
        ])
            ->assertSee('No hay establecimientos configurados para este indicador.');
    }

    private function enableMetasModuleAccessForDependency(int $depId): void
    {
        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::firstOrCreate(
            ['cen_hie_dependency_id' => $depId, 'module_key' => 'metas-18834'],
            ['enabled' => true],
        ));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::firstOrCreate(
            [
                'indicator_type_id' => $this->indicatorType->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => $depId,
            ],
            [
                'etapa_vigente' => 'AUTOEVALUACION',
                'habilitado' => true,
            ],
        ));
    }

    private function createCenter(string $codigo, string $nombre): CenCenter
    {
        return CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => $codigo,
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => $nombre,
            'nombre_corto' => $nombre,
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
    }

    private function createCenterCatalogDependencies(): void {}
}
