<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Livewire\MetasInstrument\IndicatorConfigurationComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndicatorConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private IndicatorTypeSetting $setting;

    private User $controlGestion;

    private User $adminUser;

    private User $referente1;

    private User $referente2;

    private User $parametrizador;

    private CenCenter $centerA;

    private CenCenter $centerB;

    /** @var MetIndicator[] */
    private array $indicators;

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
        $parametrizadorRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);

        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $this->adminUser->role()->associate($adminRole)->save();

        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->referente1 = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $this->referente1->role()->associate($referenteRole)->save();

        $this->referente2 = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $this->referente2->role()->associate($referenteRole)->save();

        $this->parametrizador = User::factory()->create(['role_id' => $parametrizadorRole->id, 'cen_hie_dependency_id' => 1]);
        $this->parametrizador->role()->associate($parametrizadorRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->setting = IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        $this->createCenterDependencies();
        $this->centerA = $this->createCenter('CEN-A', 'Hospital A');
        $this->centerB = $this->createCenter('CEN-B', 'Hospital B');

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerA->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerB->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->indicators = [];
        for ($i = 1; $i <= 5; $i++) {
            $this->indicators[] = MetIndicator::unguarded(fn () => MetIndicator::create([
                'indicator_type_id' => $this->indicatorType->id,
                'clave' => "MET-{$i}",
                'nombre' => "Indicador {$i}",
                'decimales' => 2,
                'year_id' => $this->year->id,
                'estado' => true,
            ]));
        }
    }

    /** @test */
    public function control_gestion_can_render_configuration_component(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorConfigurationComponent::class)
            ->assertStatus(200)
            ->assertSee('Hospital A')
            ->assertSee('Hospital B');
    }

    /** @test */
    public function parametrizador_is_forbidden_from_configuration(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorConfigurationComponent::class)
            ->assertStatus(403);
    }

    /** @test */
    public function save_fails_when_no_center_selected(): void
    {
        $configs = $this->buildConfigs(count: 5, ponderacion: '20');

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('configs', $configs)
            ->call('save')
            ->assertHasErrors(['general']);
    }

    /** @test */
    public function configuration_initializes_from_existing_db_records(): void
    {
        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->centerA->id,
            'seleccionado' => true,
            'ponderacion' => 25.00,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id);

        $configs = $component->get('configs');
        $this->assertTrue((bool) $configs[$this->indicators[0]->id]['seleccionado']);
        $this->assertEquals('25.00', $configs[$this->indicators[0]->id]['ponderacion']);
    }

    /** @test */
    public function save_fails_when_fewer_than_4_indicators_selected(): void
    {
        $configs = $this->buildConfigs(count: 5, ponderacion: '');
        $configs[$this->indicators[0]->id] = ['seleccionado' => true, 'ponderacion' => '50'];
        $configs[$this->indicators[1]->id] = ['seleccionado' => true, 'ponderacion' => '50'];

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set('configs', $configs)
            ->call('save')
            ->assertHasErrors(['general']);
    }

    /** @test */
    public function save_fails_when_ponderaciones_do_not_sum_100(): void
    {
        $configs = $this->buildConfigs(count: 5, ponderacion: '20');
        $configs[$this->indicators[4]->id]['ponderacion'] = '15'; // 20+20+20+20+15 = 95

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set('configs', $configs)
            ->call('save')
            ->assertHasErrors(['general']);
    }

    /** @test */
    public function save_succeeds_with_valid_configuration(): void
    {
        $this->updateMetasEtapa($this->setting, 'FORMULACION');
        $configs = $this->buildConfigs(count: 5, ponderacion: '20');

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set('configs', $configs)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_center_id' => $this->centerA->id,
            'seleccionado' => 1,
        ]);
    }

    /** @test */
    public function different_centers_have_independent_configurations(): void
    {
        $this->updateMetasEtapa($this->setting, 'FORMULACION');
        $configsA = $this->buildConfigs(count: 5, ponderacion: '20');
        $configsB = $this->buildConfigs(count: 5, ponderacion: '');
        $configsB[$this->indicators[0]->id] = ['seleccionado' => true, 'ponderacion' => '100'];
        for ($i = 1; $i < 5; $i++) {
            $configsB[$this->indicators[$i]->id] = ['seleccionado' => false, 'ponderacion' => ''];
        }
        // centerB: solo 1 seleccionado → debe fallar validación
        $configsB[$this->indicators[1]->id] = ['seleccionado' => true, 'ponderacion' => '0'];
        $configsB[$this->indicators[2]->id] = ['seleccionado' => true, 'ponderacion' => '0'];
        $configsB[$this->indicators[3]->id] = ['seleccionado' => true, 'ponderacion' => '0'];

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        // Guardar centerA correctamente
        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set('configs', $configsA)
            ->call('save')
            ->assertHasNoErrors();

        // centerA tiene configuraciones; centerB no
        $this->assertEquals(5, MetIndicatorConfiguration::where('cen_center_id', $this->centerA->id)->count());
        $this->assertEquals(0, MetIndicatorConfiguration::where('cen_center_id', $this->centerB->id)->count());
    }

    /** @test */
    public function add_referente_adds_user_to_list(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $indicatorId = $this->indicators[0]->id;

        $component = Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set("nuevoReferentePorIndicador.{$indicatorId}", (string) $this->referente1->id)
            ->call('addReferente', $indicatorId);

        $referentes = $component->get('referentesPorIndicador');
        $this->assertContains($this->referente1->id, $referentes[$indicatorId]);
    }

    /** @test */
    public function remove_referente_removes_user_from_list(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $indicatorId = $this->indicators[0]->id;

        $component = Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set("nuevoReferentePorIndicador.{$indicatorId}", (string) $this->referente1->id)
            ->call('addReferente', $indicatorId)
            ->call('removeReferente', $indicatorId, $this->referente1->id);

        $referentes = $component->get('referentesPorIndicador');
        $this->assertNotContains($this->referente1->id, $referentes[$indicatorId]);
    }

    /** @test */
    public function save_persists_referentes_to_database(): void
    {
        $this->updateMetasEtapa($this->setting, 'FORMULACION');
        $configs = $this->buildConfigs(count: 5, ponderacion: '20');

        $indicatorId = $this->indicators[0]->id;
        $referentesPorIndicador = [];
        foreach ($this->indicators as $indicator) {
            $referentesPorIndicador[$indicator->id] = [];
        }
        $referentesPorIndicador[$indicatorId] = [$this->referente1->id];

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set('configs', $configs)
            ->set('referentesPorIndicador', $referentesPorIndicador)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_indicator_referentes', [
            'met_indicator_id' => $indicatorId,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referente1->id,
        ]);
    }

    /** @test */
    public function duplicate_save_does_not_create_duplicate_configuration(): void
    {
        $this->updateMetasEtapa($this->setting, 'FORMULACION');
        $configs = $this->buildConfigs(count: 5, ponderacion: '20');

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set('configs', $configs)
            ->call('save');

        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set('configs', $configs)
            ->call('save');

        $count = MetIndicatorConfiguration::where('cen_center_id', $this->centerA->id)->count();
        $this->assertEquals(count($this->indicators), $count);
    }

    /** @test */
    public function referentes_are_global_across_centers(): void
    {
        $this->updateMetasEtapa($this->setting, 'FORMULACION');
        $configs = $this->buildConfigs(count: 5, ponderacion: '20');

        $indicatorId = $this->indicators[0]->id;
        $referentesPorIndicador = [];
        foreach ($this->indicators as $indicator) {
            $referentesPorIndicador[$indicator->id] = [];
        }
        $referentesPorIndicador[$indicatorId] = [$this->referente1->id];

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        // Guardar desde centerA
        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set('configs', $configs)
            ->set('referentesPorIndicador', $referentesPorIndicador)
            ->call('save');

        // Abrir el componente con centerB → los referentes deben ser los mismos (son globales por SS)
        $component = Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerB->id);

        $referentes = $component->get('referentesPorIndicador');
        $this->assertContains($this->referente1->id, $referentes[$indicatorId]);
    }

    /** @test */
    public function save_falla_cuando_hay_menos_que_min_indicadores_dinamico(): void
    {
        $this->updateMetasEtapa($this->setting, 'FORMULACION');
        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 5,
            'min_ponderacion' => 0.00,
            'max_ponderacion' => 100.00,
        ]));

        $configs = $this->buildConfigs(count: 5, ponderacion: '');
        for ($i = 0; $i < 4; $i++) {
            $configs[$this->indicators[$i]->id] = ['seleccionado' => true, 'ponderacion' => '25'];
        }

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set('configs', $configs)
            ->call('save')
            ->assertHasErrors(['general']);
    }

    /** @test */
    public function save_exitoso_con_exactamente_min_indicadores_dinamico(): void
    {
        $this->updateMetasEtapa($this->setting, 'FORMULACION');
        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 5,
            'min_ponderacion' => 0.00,
            'max_ponderacion' => 100.00,
        ]));

        $configs = $this->buildConfigs(count: 5, ponderacion: '20');

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set('configs', $configs)
            ->call('save')
            ->assertHasNoErrors();
    }

    /** @test */
    public function save_falla_cuando_ponderacion_menor_que_min(): void
    {
        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 4,
            'min_ponderacion' => 10.00,
            'max_ponderacion' => 100.00,
        ]));

        $configs = $this->buildConfigs(count: 5, ponderacion: '');
        for ($i = 0; $i < 4; $i++) {
            $configs[$this->indicators[$i]->id] = ['seleccionado' => true, 'ponderacion' => '23'];
        }
        $configs[$this->indicators[4]->id] = ['seleccionado' => true, 'ponderacion' => '8'];

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set('configs', $configs)
            ->call('save')
            ->assertHasErrors(['general']);
    }

    /** @test */
    public function save_falla_cuando_ponderacion_mayor_que_max(): void
    {
        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 4,
            'min_ponderacion' => 0.00,
            'max_ponderacion' => 40.00,
        ]));

        $configs = $this->buildConfigs(count: 5, ponderacion: '');
        for ($i = 0; $i < 4; $i++) {
            $configs[$this->indicators[$i]->id] = ['seleccionado' => true, 'ponderacion' => '10'];
        }
        $configs[$this->indicators[4]->id] = ['seleccionado' => true, 'ponderacion' => '60'];

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(IndicatorConfigurationComponent::class)
            ->set('selectedCenterId', $this->centerA->id)
            ->set('configs', $configs)
            ->call('save')
            ->assertHasErrors(['general']);
    }

    /**
     * @param  int  $count  Número de indicadores a incluir en el array
     * @param  string  $ponderacion  Valor de ponderación para cada uno
     * @return array<int, array{seleccionado: bool, ponderacion: string}>
     */
    private function buildConfigs(int $count, string $ponderacion): array
    {
        $configs = [];
        foreach (array_slice($this->indicators, 0, $count) as $indicator) {
            $configs[$indicator->id] = [
                'seleccionado' => $ponderacion !== '',
                'ponderacion' => $ponderacion,
            ];
        }

        return $configs;
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

    private function createCenterDependencies(): void
    {
        $this->seedLegacyCenCatalogRows();
    }
}
