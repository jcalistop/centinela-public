<?php

namespace Tests\Feature\MetasInstrument;

use App\Livewire\MetasInstrument\ResultsSummaryComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConfigureEstablishmentsNavigationTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private User $controlGestion;

    private User $referente;

    private User $parametrizador;

    private CenCenter $center;

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
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);

        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->referente = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $this->referente->role()->associate($referenteRole)->save();

        $this->parametrizador = User::factory()->create(['role_id' => $paramRole->id, 'cen_hie_dependency_id' => 1]);
        $this->parametrizador->role()->associate($paramRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->enableMetasModuleAccessForDependency(1);

        $this->seedLegacyCenCatalogRows();

        $this->center = $this->createMinimalCenCenter([
            'codigo' => 'C-A',
            'nombre_corto' => 'Hospital A',
            'nombre_oficial' => 'Hospital A',
            'cen_hie_dependency_id' => 1,
        ]);

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));
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

    /** @test */
    public function hub_no_muestra_tab_participantes(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.index', $this->indicatorType))
            ->assertOk()
            ->assertDontSee('id="tab-establecimientos"', false)
            ->assertDontSee('>Participantes</a>', false);
    }

    /** @test */
    public function resultados_muestra_cta_configurar_establecimientos_para_cg(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(ResultsSummaryComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertSee('Configurar establecimientos')
            ->assertSee(route('metas.instrument.establishments.configure', $this->indicatorType), false);
    }

    /** @test */
    public function resultados_oculta_cta_para_referente(): void
    {
        $this->actingAs($this->referente)->withSession(['indicator_year' => $this->year]);

        Livewire::test(ResultsSummaryComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])->assertDontSee('Configurar establecimientos');
    }

    /** @test */
    public function cg_accede_a_configurar_establecimientos_e_indicadores(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.establishments.configure', $this->indicatorType))
            ->assertOk()
            ->assertSee('Configurar establecimientos')
            ->assertSeeLivewire('metas-instrument.center-selection-component')
            ->assertSee('Responsables', false);

        $this->get(route('metas.instrument.establishments.indicators', [
            'indicatorType' => $this->indicatorType,
            'center' => $this->center->id,
        ]))
            ->assertOk()
            ->assertSee('Indicadores')
            ->assertSee($this->center->nombre_corto);
    }

    /** @test */
    public function referente_recibe_403_en_rutas_de_configuracion(): void
    {
        $this->actingAs($this->referente)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.establishments.configure', $this->indicatorType))
            ->assertForbidden();

        $this->get(route('metas.instrument.establishments.indicators', [
            'indicatorType' => $this->indicatorType,
            'center' => $this->center->id,
        ]))->assertForbidden();
    }

    /** @test */
    public function parametrizador_recibe_403_en_rutas_de_configuracion(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.establishments.configure', $this->indicatorType))
            ->assertForbidden();
    }
}
