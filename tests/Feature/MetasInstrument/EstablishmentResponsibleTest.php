<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Livewire\MetasInstrument\CenterSelectionComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetEstablishmentResponsible;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EstablishmentResponsibleTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private User $controlGestion;

    private User $responsibleA;

    private User $responsibleB;

    private CenCenter $centerA;

    private CenCenter $centerB;

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

        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->responsibleA = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $this->responsibleA->role()->associate($referenteRole)->save();

        $this->responsibleB = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $this->responsibleB->role()->associate($referenteRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 4,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
        ]));

        $this->enableMetasModuleAccessForDependency(1);

        $this->seedLegacyCenCatalogRows();

        $this->centerA = $this->createMinimalCenCenter([
            'codigo' => 'CEN-A',
            'nombre_oficial' => 'Hospital A',
            'nombre_corto' => 'Hospital A',
            'cen_hie_dependency_id' => 1,
        ]);

        $this->centerB = $this->createMinimalCenCenter([
            'codigo' => 'CEN-B',
            'nombre_oficial' => 'Hospital B',
            'nombre_corto' => 'Hospital B',
            'cen_hie_dependency_id' => 1,
        ]);

        foreach ([$this->centerA, $this->centerB] as $center) {
            MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
                'cen_center_id' => $center->id,
                'year_id' => $this->year->id,
                'indicator_type_id' => $this->indicatorType->id,
                'cen_hie_dependency_id' => 1,
            ]));
        }
    }

    /** @test */
    public function control_gestion_designa_dos_responsables_en_el_mismo_centro(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openDesignateModal', $this->centerA->id)
            ->call('saveDesignation', $this->responsibleA->id)
            ->call('openDesignateModal', $this->centerA->id)
            ->call('saveDesignation', $this->responsibleB->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_establishment_responsibles', [
            'cen_center_id' => $this->centerA->id,
            'user_id' => $this->responsibleA->id,
        ]);
        $this->assertDatabaseHas('met_establishment_responsibles', [
            'cen_center_id' => $this->centerA->id,
            'user_id' => $this->responsibleB->id,
        ]);
    }

    /** @test */
    public function no_permite_designar_usuario_ya_responsable_de_otro_centro(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openDesignateModal', $this->centerA->id)
            ->call('saveDesignation', $this->responsibleA->id)
            ->call('openDesignateModal', $this->centerB->id)
            ->call('saveDesignation', $this->responsibleA->id);

        $this->assertDatabaseMissing('met_establishment_responsibles', [
            'cen_center_id' => $this->centerB->id,
            'user_id' => $this->responsibleA->id,
        ]);
    }

    /** @test */
    public function responsable_accede_vista_indicadores_de_su_centro(): void
    {
        $this->designate($this->centerA, $this->responsibleA);
        $this->enableFormulacion();

        $this->actingAs($this->responsibleA)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerA->id,
        ])->assertOk()
            ->assertSee('Ponderación', false);
    }

    /** @test */
    public function responsable_recibe_403_en_vista_indicadores_de_otro_centro(): void
    {
        $this->designate($this->centerA, $this->responsibleA);

        $this->actingAs($this->responsibleA)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerB->id,
        ])->assertForbidden();
    }

    /** @test */
    public function responsable_puede_guardar_convenio_y_ponderaciones(): void
    {
        $this->designate($this->centerA, $this->responsibleA);
        $this->enableFormulacion();

        $indicators = collect([
            $this->createIndicator('R-1'),
            $this->createIndicator('R-2'),
            $this->createIndicator('R-3'),
            $this->createIndicator('R-4'),
        ]);

        foreach ($indicators as $indicator) {
            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_center_id' => $this->centerA->id,
                'cen_hie_dependency_id' => 1,
                'aplica' => true,
                'agrupa_ss' => false,
                'seleccionado' => false,
                'ponderacion' => null,
                'meta_efectiva' => 50,
            ]));
        }

        $this->actingAs($this->responsibleA)->withSession(['indicator_year' => $this->year]);

        $component = Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerA->id,
        ]);

        foreach ($indicators as $indicator) {
            $component
                ->set("configs.{$indicator->id}.seleccionado", true)
                ->set("configs.{$indicator->id}.ponderacion", '25');
        }

        $component->call('saveConfig')
            ->assertHasNoErrors()
            ->assertRedirect(route('metas.instrument.establishment.results', [
                'indicatorType' => $this->indicatorType->route,
                'center' => $this->centerA->id,
            ]));

        foreach ($indicators as $indicator) {
            $this->assertDatabaseHas('met_indicator_configurations', [
                'met_indicator_id' => $indicator->id,
                'cen_center_id' => $this->centerA->id,
                'seleccionado' => true,
                'ponderacion' => 25,
                'meta_efectiva' => 50,
            ]);
        }
    }

    /** @test */
    public function responsable_puede_actualizar_solo_ponderaciones_cuando_ya_estan_en_convenio(): void
    {
        $this->designate($this->centerA, $this->responsibleA);
        $this->enableFormulacion();

        $indicators = collect([
            $this->createIndicator('R-1'),
            $this->createIndicator('R-2'),
            $this->createIndicator('R-3'),
            $this->createIndicator('R-4'),
        ]);

        foreach ($indicators as $index => $indicator) {
            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_center_id' => $this->centerA->id,
                'cen_hie_dependency_id' => 1,
                'aplica' => true,
                'agrupa_ss' => false,
                'seleccionado' => true,
                'ponderacion' => 25,
                'meta_efectiva' => 50,
            ]));
        }

        $this->actingAs($this->responsibleA)->withSession(['indicator_year' => $this->year]);

        $component = Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerA->id,
        ]);

        foreach ($indicators as $indicator) {
            $component->set("configs.{$indicator->id}.ponderacion", '25');
        }

        $component->call('saveConfig')->assertHasNoErrors();

        foreach ($indicators as $indicator) {
            $this->assertDatabaseHas('met_indicator_configurations', [
                'met_indicator_id' => $indicator->id,
                'cen_center_id' => $this->centerA->id,
                'ponderacion' => 25,
                'meta_efectiva' => 50,
            ]);
        }
    }

    /** @test */
    public function responsable_no_puede_mutar_meta_efectiva_via_livewire(): void
    {
        $this->designate($this->centerA, $this->responsibleA);

        $indicator = $this->createIndicator('R-M');

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_center_id' => $this->centerA->id,
            'cen_hie_dependency_id' => 1,
            'aplica' => true,
            'agrupa_ss' => false,
            'seleccionado' => true,
            'ponderacion' => 100,
            'meta_efectiva' => 50,
        ]));

        $this->actingAs($this->responsibleA)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerA->id,
        ])
            ->set("configs.{$indicator->id}.meta_efectiva", '99')
            ->set("configs.{$indicator->id}.ponderacion", '100')
            ->call('saveConfig');

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerA->id,
            'meta_efectiva' => 50,
        ]);
    }

    /** @test */
    public function responsable_no_puede_mutar_aplica_via_livewire(): void
    {
        $this->designate($this->centerA, $this->responsibleA);

        $indicator = $this->createIndicator('R-X');

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_center_id' => $this->centerA->id,
            'cen_hie_dependency_id' => 1,
            'aplica' => true,
            'agrupa_ss' => false,
            'seleccionado' => true,
            'ponderacion' => 100,
            'meta_efectiva' => 50,
        ]));

        $this->actingAs($this->responsibleA)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerA->id,
        ])
            ->set("configs.{$indicator->id}.aplica", false)
            ->set("configs.{$indicator->id}.ponderacion", '100')
            ->call('saveConfig');

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->centerA->id,
            'aplica' => true,
        ]);
    }

    /** @test */
    public function responsable_ve_cta_definir_ponderaciones_en_resultados_por_establecimiento(): void
    {
        $this->designate($this->centerA, $this->responsibleA);
        $this->enableFormulacion();

        $this->actingAs($this->responsibleA)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.establishment.results', [
            'indicatorType' => $this->indicatorType,
            'center' => $this->centerA,
        ]))
            ->assertOk()
            ->assertSee('Configurar convenio', false)
            ->assertSee(route('metas.instrument.establishments.indicators', [
                'indicatorType' => $this->indicatorType,
                'center' => $this->centerA,
            ], false), false);
    }

    /** @test */
    public function control_gestion_no_ve_cta_convenio_en_resultados_por_establecimiento(): void
    {
        $this->enableFormulacion();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.establishment.results', [
            'indicatorType' => $this->indicatorType,
            'center' => $this->centerA,
        ]))
            ->assertOk()
            ->assertDontSee('Configurar convenio', false);
    }

    /** @test */
    public function referente_sin_designacion_no_ve_cta_ponderaciones_en_resultados(): void
    {
        $this->actingAs($this->responsibleA)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.establishment.results', [
            'indicatorType' => $this->indicatorType,
            'center' => $this->centerA,
        ]))
            ->assertOk()
            ->assertDontSee('Configurar convenio', false);
    }

    private function enableFormulacion(): void
    {
        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::query()
            ->where('indicator_type_id', $this->indicatorType->id)
            ->where('year_id', $this->year->id)
            ->where('cen_hie_dependency_id', 1)
            ->update(['etapa_vigente' => EtapaVigente::Formulacion->value]));
    }

    private function designate(CenCenter $center, User $user): void
    {
        MetEstablishmentResponsible::unguarded(fn () => MetEstablishmentResponsible::create([
            'cen_center_id' => $center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $user->id,
            'assigned_by' => $this->controlGestion->id,
        ]));
    }

    private function createIndicator(string $clave): MetIndicator
    {
        return MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => $clave,
            'nombre' => 'Indicador '.$clave,
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => 50.0,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));
    }

    private function enableMetasModuleAccessForDependency(int $depId): void
    {
        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::firstOrCreate(
            ['cen_hie_dependency_id' => $depId, 'module_key' => 'metas-18834'],
            ['enabled' => true],
        ));
    }
}
