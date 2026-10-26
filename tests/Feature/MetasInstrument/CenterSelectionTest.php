<?php

namespace Tests\Feature\MetasInstrument;

use App\Livewire\MetasInstrument\CenterSelectionComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterAdosado;
use App\Models\MetCenterInclusion;
use App\Models\MetEstablishmentResult;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMeasurementUnit;
use App\Models\MetUnitType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CenterSelectionTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private IndicatorTypeSetting $setting;

    private User $controlGestion;

    private User $adminUser;

    private User $referente;

    private CenCenter $centerA;

    private CenCenter $centerB;

    private CenCenter $adosadoDeA;

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
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $this->adminUser->role()->associate($adminRole)->save();

        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->referente = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $this->referente->role()->associate($referenteRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->setting = IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        $this->createCenterDependencies();

        $this->centerA = $this->createCenter('CEN-A', 'Hospital A', null);
        $this->centerB = $this->createCenter('CEN-B', 'Hospital B', null);
        $this->adosadoDeA = $this->createCenter('CEN-A1', 'CECOSF A1', 'CEN-A');
    }

    /** @test */
    public function control_gestion_can_render_component(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->assertStatus(200)
            ->assertSee('Hospital A')
            ->assertSee('Hospital B');
    }

    /** @test */
    public function referente_is_forbidden(): void
    {
        $this->actingAs($this->referente)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->assertStatus(403);
    }

    /** @test */
    public function initially_no_centers_are_included(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = Livewire::test(CenterSelectionComponent::class);

        $this->assertEmpty($component->get('includedCenterIds'));
        $this->assertDatabaseCount('met_center_inclusions', 0);
    }

    /** @test */
    public function toggle_includes_a_center(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('toggleCenter', $this->centerA->id)
            ->assertSet('pendingRemoveCenterId', null);

        $this->assertDatabaseHas('met_center_inclusions', [
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
        ]);
    }

    /** @test */
    public function toggle_excludes_an_included_center_without_results(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('toggleCenter', $this->centerA->id);

        $this->assertDatabaseMissing('met_center_inclusions', [
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
        ]);
    }

    /** @test */
    public function removing_center_with_results_requires_confirmation(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'TRIM-1',
            'resultado_global' => 85.00,
            'n_seleccionados' => 4,
            'n_calculados' => 4,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        // Primera llamada: debe solicitar confirmación, NO eliminar
        Livewire::test(CenterSelectionComponent::class)
            ->call('toggleCenter', $this->centerA->id)
            ->assertSet('pendingRemoveCenterId', $this->centerA->id);

        $this->assertDatabaseHas('met_center_inclusions', [
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
        ]);
    }

    /** @test */
    public function confirmed_removal_deletes_inclusion_even_with_results(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'TRIM-1',
            'resultado_global' => 85.00,
            'n_seleccionados' => 4,
            'n_calculados' => 4,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        // Primera llamada: solicita confirmación
        $component = Livewire::test(CenterSelectionComponent::class)
            ->call('toggleCenter', $this->centerA->id)
            ->assertSet('pendingRemoveCenterId', $this->centerA->id);

        // Segunda llamada: confirma
        $component->call('toggleCenter', $this->centerA->id);

        $this->assertDatabaseMissing('met_center_inclusions', [
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
        ]);

        // El resultado histórico sigue existiendo
        $this->assertDatabaseHas('met_establishment_results', [
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
        ]);
    }

    /** @test */
    public function cancel_remove_clears_pending_state(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetEstablishmentResult::unguarded(fn () => MetEstablishmentResult::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
            'periodo' => 'TRIM-1',
            'resultado_global' => 90.00,
            'n_seleccionados' => 4,
            'n_calculados' => 4,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('toggleCenter', $this->centerA->id)
            ->assertSet('pendingRemoveCenterId', $this->centerA->id)
            ->call('cancelRemove')
            ->assertSet('pendingRemoveCenterId', null);

        $this->assertDatabaseHas('met_center_inclusions', [
            'cen_center_id' => $this->centerA->id,
        ]);
    }

    /** @test */
    public function toggle_is_blocked_when_instrument_is_cerrado(): void
    {
        IndicatorTypeSetting::unguarded(fn () => $this->setting->update(['etapa_vigente' => 'CERRADO']));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('toggleCenter', $this->centerA->id)
            ->assertHasErrors('general');

        $this->assertDatabaseMissing('met_center_inclusions', [
            'cen_center_id' => $this->centerA->id,
        ]);
    }

    /** @test */
    public function inclusion_is_scoped_per_instrument(): void
    {
        $otherInstrument = IndicatorType::firstOrCreate(
            ['route' => 'metas-19664'],
            [
                'clave_indicador' => 'metas-19664',
                'nombre_indicador' => 'Metas 19.664',
                'descripcion_indicador' => 'Metas sanitarias',
                'icon_class' => 'voyager-book',
                'view' => null,
                'estado' => 1,
                'orden' => 4,
            ]
        );

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('toggleCenter', $this->centerA->id);

        $this->assertDatabaseHas('met_center_inclusions', [
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
        ]);

        $this->assertDatabaseMissing('met_center_inclusions', [
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $otherInstrument->id,
        ]);
    }

    /** @test */
    public function adosados_are_shown_under_their_parent_center(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->centerA->id,
            'cen_center_adosado_id' => $this->adosadoDeA->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->assertSee('CECOSF A1');
    }

    /** @test */
    public function centers_with_other_adm_dependency_are_not_shown(): void
    {
        $this->createMinimalCenCenter([
            'codigo' => 'CEN-MUNI',
            'nombre_oficial' => 'CESFAM Municipal',
            'nombre_corto' => 'MUNI',
            'cen_adm_dependency' => 'Municipal',
        ]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->assertSee('Hospital A')
            ->assertDontSee('CESFAM Municipal');
    }

    /** @test */
    public function component_only_shows_centers_from_authenticated_user_ss(): void
    {
        // Centro de otro SS
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS02',
            'nombre' => 'Otro Servicio',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->createCenter('CEN-OTHER', 'Hospital Otro SS', null, 2);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->assertSee('Hospital A')
            ->assertDontSee('Hospital Otro SS');
    }

    private function createCenter(string $codigo, string $nombre, ?string $codigoMadre, int $depId = 1): CenCenter
    {
        return $this->createMinimalCenCenter([
            'codigo' => $codigo,
            'codigo_madre' => $codigoMadre,
            'cen_hie_dependency_id' => $depId,
            'nombre_oficial' => $nombre,
            'nombre_corto' => $nombre,
        ]);
    }

    private function createCenterDependencies(): void
    {
        $this->seedLegacyCenCatalogRows();
    }

    private function createIndicator(string $clave, float $metaValor = 50.0): MetIndicator
    {
        return MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => $clave,
            'nombre' => 'Indicador '.$clave,
            'decimales' => 2,
            'decimales_modo' => 'redondear',
            'meta_valor' => $metaValor,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));
    }

    private function indicatorsConfigComponent(?CenCenter $center = null)
    {
        $center ??= $this->centerA;

        return Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'standaloneConfigPage' => true,
            'configCenterId' => $center->id,
        ]);
    }

    private function setEtapaFormulacion(): void
    {
        IndicatorTypeSetting::unguarded(fn () => $this->setting->update([
            'etapa_vigente' => 'FORMULACION',
            'habilitado' => 1,
        ]));
    }

    /** @test */
    public function opening_config_modal_initializes_meta_efectiva_with_meta_nacional(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $indicator = $this->createIndicator('MET-01', 75.5);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = $this->indicatorsConfigComponent();

        $configs = $component->get('configs');
        $this->assertArrayHasKey($indicator->id, $configs);
        $this->assertSame('75.50', $configs[$indicator->id]['meta_efectiva']);
        $this->assertFalse((bool) ($configs[$indicator->id]['aplica'] ?? true));
    }

    /** @test */
    public function saving_config_persists_meta_efectiva(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $ind1 = $this->createIndicator('MET-A', 60.0);
        $ind2 = $this->createIndicator('MET-B', 80.0);
        $ind3 = $this->createIndicator('MET-C', 70.0);
        $ind4 = $this->createIndicator('MET-D', 90.0);

        $this->setEtapaFormulacion();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->indicatorsConfigComponent()
            ->set("configs.{$ind1->id}.aplica", true)
            ->set("configs.{$ind1->id}.seleccionado", true)
            ->set("configs.{$ind1->id}.ponderacion", '25')
            ->set("configs.{$ind1->id}.meta_efectiva", '55.5')
            ->set("configs.{$ind2->id}.aplica", true)
            ->set("configs.{$ind2->id}.seleccionado", true)
            ->set("configs.{$ind2->id}.ponderacion", '25')
            ->set("configs.{$ind2->id}.meta_efectiva", '80')
            ->set("configs.{$ind3->id}.aplica", true)
            ->set("configs.{$ind3->id}.seleccionado", true)
            ->set("configs.{$ind3->id}.ponderacion", '25')
            ->set("configs.{$ind3->id}.meta_efectiva", '70')
            ->set("configs.{$ind4->id}.aplica", true)
            ->set("configs.{$ind4->id}.seleccionado", true)
            ->set("configs.{$ind4->id}.ponderacion", '25')
            ->set("configs.{$ind4->id}.meta_efectiva", '90')
            ->call('saveConfig');

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $ind1->id,
            'cen_center_id' => $this->centerA->id,
            'meta_efectiva' => 55.5,
        ]);
    }

    /** @test */
    public function loading_existing_config_shows_saved_meta_efectiva(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $indicator = $this->createIndicator('MET-X', 50.0);

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_center_id' => $this->centerA->id,
            'cen_hie_dependency_id' => 1,
            'seleccionado' => true,
            'ponderacion' => 25.00,
            'meta_efectiva' => 45.25,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = $this->indicatorsConfigComponent();

        $configs = $component->get('configs');
        $this->assertSame('45.25', $configs[$indicator->id]['meta_efectiva']);
    }

    /** @test */
    public function loading_existing_config_without_meta_efectiva_falls_back_to_meta_nacional(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $indicator = $this->createIndicator('MET-Y', 65.0);

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_center_id' => $this->centerA->id,
            'cen_hie_dependency_id' => 1,
            'seleccionado' => true,
            'ponderacion' => 25.00,
            'meta_efectiva' => null,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = $this->indicatorsConfigComponent();

        $configs = $component->get('configs');
        $this->assertSame('65.00', $configs[$indicator->id]['meta_efectiva']);
    }

    /** @test */
    public function tab_establecimientos_muestra_alerta_si_incluido_sin_configuracion(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->assertSee('metas-establishment-config-alert', false)
            ->assertSee('Configuración incompleta', false)
            ->assertSee('Sin indicadores en convenio configurados', false);
    }

    /** @test */
    public function tab_establecimientos_no_muestra_alerta_con_configuracion_completa(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $indicators = collect(range(1, 4))->map(fn (int $n) => $this->createIndicator('MET-CFG-'.$n, 50.0));

        foreach ($indicators as $indicator) {
            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_center_id' => $this->centerA->id,
                'cen_hie_dependency_id' => 1,
                'aplica' => true,
                'seleccionado' => true,
                'ponderacion' => 25.00,
                'meta_efectiva' => 50.00,
            ]));
        }

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->assertDontSee('metas-establishment-config-alert', false);
    }

    /** @test */
    public function tab_establecimientos_muestra_alerta_por_meta_sin_registrar(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $indicators = collect(range(1, 4))->map(fn (int $n) => $this->createIndicator('MET-META-'.$n, 50.0));

        foreach ($indicators as $index => $indicator) {
            MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
                'met_indicator_id' => $indicator->id,
                'year_id' => $this->year->id,
                'cen_center_id' => $this->centerA->id,
                'cen_hie_dependency_id' => 1,
                'aplica' => true,
                'seleccionado' => true,
                'ponderacion' => 25.00,
                'meta_efectiva' => $index === 0 ? null : 50.00,
            ]));
        }

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->assertSee('metas-establishment-config-alert', false)
            ->assertSee('meta efectiva sin registrar', false);
    }

    /** @test */
    public function config_modal_header_toggles_all_aplica_column(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $ind1 = $this->createIndicator('MET-ALL-1', 60.0);
        $ind2 = $this->createIndicator('MET-ALL-2', 70.0);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = $this->indicatorsConfigComponent();

        $component->call('toggleAllConfigAplica');

        $configs = $component->get('configs');
        $this->assertTrue((bool) $configs[$ind1->id]['aplica']);
        $this->assertTrue((bool) $configs[$ind2->id]['aplica']);

        $component->call('toggleAllConfigAplica');

        $configs = $component->get('configs');
        $this->assertFalse((bool) $configs[$ind1->id]['aplica']);
        $this->assertFalse((bool) $configs[$ind2->id]['aplica']);
        $this->assertSame('', $configs[$ind1->id]['meta_efectiva']);
    }

    /** @test */
    public function config_modal_header_toggles_agrupa_ss_only_for_aplica_rows(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $ind1 = $this->createIndicator('MET-AGR-1', 60.0);
        $ind2 = $this->createIndicator('MET-AGR-2', 70.0);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = $this->indicatorsConfigComponent()
            ->set("configs.{$ind1->id}.aplica", true)
            ->set("configs.{$ind2->id}.aplica", false);

        $component->call('toggleAllConfigAgrupaSs');

        $configs = $component->get('configs');
        $this->assertTrue((bool) $configs[$ind1->id]['agrupa_ss']);
        $this->assertFalse((bool) $configs[$ind2->id]['agrupa_ss']);
    }

    /** @test */
    public function config_modal_header_agrupa_and_convenido_disabled_when_no_aplica(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $ind1 = $this->createIndicator('MET-NO-APL-1', 60.0);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = $this->indicatorsConfigComponent();

        $html = $component->html();
        $this->assertMatchesRegularExpression(
            '/wire:key="hdr-agrupa-off"/s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/wire:key="hdr-convenido-off"/s',
            $html,
        );

        $component
            ->call('toggleAllConfigAplica')
            ->call('toggleAllConfigAgrupaSs')
            ->call('toggleAllConfigConvenido');

        $configs = $component->get('configs');
        $this->assertTrue((bool) $configs[$ind1->id]['agrupa_ss']);
        $this->assertTrue((bool) $configs[$ind1->id]['seleccionado']);

        $component->call('toggleAllConfigAplica');

        $configs = $component->get('configs');
        $this->assertFalse((bool) $configs[$ind1->id]['aplica']);
        $this->assertFalse((bool) $configs[$ind1->id]['agrupa_ss']);
        $this->assertFalse((bool) $configs[$ind1->id]['seleccionado']);

        $html = $component->html();
        $this->assertMatchesRegularExpression(
            '/wire:key="hdr-agrupa-off"/s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/wire:key="hdr-convenido-off"/s',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/wire:key="hdr-agrupa-on-/s',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/wire:key="hdr-convenido-on-/s',
            $html,
        );
    }

    /** @test */
    public function config_modal_clears_stale_agrupa_and_convenido_when_last_aplica_is_unchecked(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $ind1 = $this->createIndicator('MET-STALE-1', 60.0);
        $ind2 = $this->createIndicator('MET-STALE-2', 70.0);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = $this->indicatorsConfigComponent()
            ->set("configs.{$ind1->id}.agrupa_ss", true)
            ->set("configs.{$ind1->id}.seleccionado", true)
            ->set("configs.{$ind2->id}.aplica", true)
            ->set("configs.{$ind2->id}.agrupa_ss", true)
            ->set("configs.{$ind2->id}.seleccionado", true)
            ->set("configs.{$ind2->id}.aplica", false);

        $configs = $component->get('configs');
        $this->assertFalse((bool) $configs[$ind1->id]['agrupa_ss']);
        $this->assertFalse((bool) $configs[$ind1->id]['seleccionado']);
        $this->assertFalse((bool) $configs[$ind2->id]['agrupa_ss']);
        $this->assertFalse((bool) $configs[$ind2->id]['seleccionado']);
    }

    /** @test */
    public function config_modal_shows_meta_efectiva_before_ponderacion(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->createIndicator('MET-ORD-1', 60.0);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $html = $this->indicatorsConfigComponent()
            ->html();

        $this->assertMatchesRegularExpression(
            '/En convenio<\/span>.*?Meta efectiva<\/th>\s*<th[^>]*>Ponderaci/s',
            $html,
        );
    }

    /** @test */
    public function hub_intro_explica_acciones_y_sin_alerta_agrupacion_adosados(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'instrumentNombre' => $this->indicatorType->nombre_indicador,
        ])
            ->assertSee('Acciones por fila')
            ->assertSee('Indicadores:')
            ->assertSee('Adosados:')
            ->assertDontSee('La agrupación de adosados aplica solo a este instrumento');
    }

    /** @test */
    public function modal_indicadores_muestra_leyenda_columnas_aplica_agrupa_convenido(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->indicatorsConfigComponent()
            ->assertSee('Significado de las columnas')
            ->assertSee('Aplica:')
            ->assertSee('Agrupa SS:')
            ->assertSee('En convenio:');
    }

    /** @test */
    public function internal_unit_modal_muestra_tabs_con_badges_por_unidad(): void
    {
        $internalType = $this->createInternalUnitInstrumentType();
        $unitType = MetUnitType::create([
            'codigo' => 'T-CS',
            'nombre' => 'Tipo CS',
            'orden' => 1,
            'enabled' => true,
        ]);

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $internalType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'URG-1',
            'nombre' => 'Urgencias',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'PAB-2',
            'nombre' => 'Pabellón',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $internalType->id,
            'instrumentNombre' => $internalType->nombre_indicador,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerA->id,
        ])
            ->assertSee('metas-config-unit-tabs', false)
            ->assertSee('URG-1')
            ->assertSee('PAB-2')
            ->assertSee('unidades completas')
            ->assertDontSee('config-unit-selector', false)
            ->assertDontSee('id="config-unit-selector"')
            ->assertDontSee('Agrupa SS');
    }

    /** @test */
    public function establishment_modal_no_muestra_tabs_de_unidad(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->indicatorsConfigComponent()
            ->assertDontSee('metas-config-unit-tabs', false)
            ->assertDontSee('unidades completas');
    }

    /** @test */
    public function banner_lista_nombres_de_establecimientos_con_configuracion_incompleta(): void
    {
        $internalType = $this->createInternalUnitInstrumentType();

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $internalType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $internalType->id,
            'instrumentNombre' => $internalType->nombre_indicador,
        ])
            ->assertSee('establecimiento(s) con configuración incompleta:')
            ->assertSee('Hospital A')
            ->assertSee('Sin unidades de medición registradas');
    }

    /** @test */
    public function cambiar_tab_de_unidad_actualiza_config_measurement_unit_id(): void
    {
        $internalType = $this->createInternalUnitInstrumentType();
        $unitType = MetUnitType::create([
            'codigo' => 'T-SW',
            'nombre' => 'Tipo SW',
            'orden' => 1,
            'enabled' => true,
        ]);

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $internalType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $unitA = MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'A-1',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        $unitB = MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'B-2',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $internalType->id,
            'instrumentNombre' => $internalType->nombre_indicador,
            'standaloneConfigPage' => true,
            'configCenterId' => $this->centerA->id,
        ])
            ->assertSet('configMeasurementUnitId', $unitA->id)
            ->call('selectConfigMeasurementUnit', $unitB->id)
            ->assertSet('configMeasurementUnitId', $unitB->id);
    }

    /** @test */
    public function save_config_allows_aplica_without_convenidos(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $ind1 = $this->createIndicator('MET-AP-1', 60.0);
        $ind2 = $this->createIndicator('MET-AP-2', 70.0);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->indicatorsConfigComponent()
            ->set("configs.{$ind1->id}.aplica", true)
            ->set("configs.{$ind1->id}.agrupa_ss", true)
            ->set("configs.{$ind1->id}.meta_efectiva", '55')
            ->set("configs.{$ind1->id}.seleccionado", false)
            ->set("configs.{$ind2->id}.aplica", true)
            ->set("configs.{$ind2->id}.seleccionado", false)
            ->call('saveConfig')
            ->assertHasNoErrors('configGeneral');

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $ind1->id,
            'cen_center_id' => $this->centerA->id,
            'aplica' => 1,
            'agrupa_ss' => 1,
            'seleccionado' => 0,
            'meta_efectiva' => 55,
        ]);

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $ind2->id,
            'cen_center_id' => $this->centerA->id,
            'aplica' => 1,
            'seleccionado' => 0,
        ]);
    }

    /** @test */
    public function save_config_fails_when_partial_convenidos_below_min(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $ind1 = $this->createIndicator('MET-PART-1', 60.0);
        $ind2 = $this->createIndicator('MET-PART-2', 70.0);

        $this->setEtapaFormulacion();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->indicatorsConfigComponent()
            ->set("configs.{$ind1->id}.aplica", true)
            ->set("configs.{$ind1->id}.seleccionado", true)
            ->set("configs.{$ind1->id}.ponderacion", '50')
            ->set("configs.{$ind2->id}.aplica", true)
            ->set("configs.{$ind2->id}.seleccionado", true)
            ->set("configs.{$ind2->id}.ponderacion", '50')
            ->call('saveConfig')
            ->assertHasErrors('configGeneral');
    }

    /** @test */
    public function save_config_fails_when_convenidos_sum_not_100(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $inds = collect(range(1, 4))->map(fn (int $n) => $this->createIndicator('MET-SUM-'.$n, 50.0));

        $this->setEtapaFormulacion();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = $this->indicatorsConfigComponent();

        foreach ($inds as $i => $ind) {
            $component
                ->set("configs.{$ind->id}.aplica", true)
                ->set("configs.{$ind->id}.seleccionado", true)
                ->set("configs.{$ind->id}.ponderacion", $i === 0 ? '10' : '25');
        }

        $component->call('saveConfig')->assertHasErrors('configGeneral');
    }

    /** @test */
    public function config_modal_copy_explains_aplica_without_convenio(): void
    {
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->createIndicator('MET-COPY-1', 50.0);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->indicatorsConfigComponent()
            ->assertSee('No es obligatorio incluir indicadores en el convenio', false)
            ->assertDontSee('La suma de ponderaciones debe ser', false);
    }

    private function createInternalUnitInstrumentType(): IndicatorType
    {
        $type = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'metas-20707-cs-'.uniqid(),
            'nombre_indicador' => 'Metas 20.707 CS',
            'route' => 'metas-20707-cs-'.uniqid(),
            'view' => null,
            'estado' => true,
            'orden' => 97,
            'measurement_granularity' => 'internal_unit',
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 4,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
        ]));

        return $type;
    }
}
