<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Livewire\MetasInstrument\CenterSelectionComponent;
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
use App\Models\MetIndicatorReferente;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use TCG\Voyager\Models\DataType;
use Tests\TestCase;

class IndicatorApplicabilityTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private IndicatorTypeSetting $setting;

    private User $controlGestion;

    private User $referenteUser;

    private CenCenter $center;

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

        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->referenteUser = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $this->referenteUser->role()->associate($referenteRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->setting = IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        $this->createCenterDependencies();
        $this->center = $this->createCenter('CEN-A', 'Hospital A');

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->center->id,
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

        // DataType de Voyager necesario para el sidebar (MenuComponent)
        DataType::unguarded(fn () => DataType::create([
            'name' => 'indicator_types',
            'slug' => 'instrumentos-evaluacion',
            'display_name_singular' => 'Tipo de indicador',
            'display_name_plural' => 'Tipos de indicador',
            'model_name' => IndicatorType::class,
            'generate_permissions' => 0,
            'server_side' => 0,
        ]));
    }

    /** @test */
    public function aplica_false_forces_seleccionado_false_on_save(): void
    {
        $this->updateMetasEtapa($this->setting, 'FORMULACION');
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        // Indicadores 1-4 aplica=true, seleccionado=true, ponderacion=25; indicador 0 aplica=false
        $configs = [];
        foreach ($this->indicators as $idx => $indicator) {
            $configs[$indicator->id] = [
                'aplica' => $idx !== 0,
                'seleccionado' => true,
                'ponderacion' => $idx !== 0 ? '25' : '0',
                'meta_efectiva' => '',
            ];
        }

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openConfigModal', $this->center->id, $this->center->nombre_corto)
            ->set('configs', $configs)
            ->call('saveConfig')
            ->assertHasNoErrors('configGeneral');

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $this->indicators[0]->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'aplica' => 0,
            'seleccionado' => 0,
        ]);
    }

    /** @test */
    public function save_config_persists_aplica_true(): void
    {
        $this->updateMetasEtapa($this->setting, 'FORMULACION');
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $configs = $this->buildConfigs(aplica: true);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openConfigModal', $this->center->id, $this->center->nombre_corto)
            ->set('configs', $configs)
            ->call('saveConfig')
            ->assertHasNoErrors('configGeneral');

        $this->assertDatabaseHas('met_indicator_configurations', [
            'met_indicator_id' => $this->indicators[0]->id,
            'cen_center_id' => $this->center->id,
            'aplica' => 1,
            'seleccionado' => 1,
        ]);
    }

    /** @test */
    public function existing_config_row_loads_aplica_from_database(): void
    {
        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => true,
            'ponderacion' => 20.00,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openConfigModal', $this->center->id, $this->center->nombre_corto);

        $configs = $component->get('configs');
        $this->assertTrue((bool) $configs[$this->indicators[0]->id]['aplica']);
        $this->assertTrue((bool) $configs[$this->indicators[0]->id]['seleccionado']);
    }

    /** @test */
    public function monthly_data_entry_blocks_access_when_aplica_false(): void
    {
        $this->createDataFieldsForIndicator($this->indicators[0]);

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => false,
            'seleccionado' => false,
        ]));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referenteUser->id,
        ]));

        $this->actingAs($this->referenteUser)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicators[0]->id,
            'centerId' => $this->center->id,
        ])->assertStatus(403);
    }

    /** @test */
    public function monthly_data_entry_allows_control_gestion_when_aplica_false(): void
    {
        $this->createDataFieldsForIndicator($this->indicators[0]);

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => false,
            'seleccionado' => false,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicators[0]->id,
            'centerId' => $this->center->id,
        ])->assertStatus(200);
    }

    /** @test */
    public function monthly_data_entry_allows_access_when_aplica_true(): void
    {
        $this->createDataFieldsForIndicator($this->indicators[0]);

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => true,
        ]));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referenteUser->id,
        ]));

        $this->seedCompleteFieldSsConfigsForIndicator($this->indicators[0]);

        $this->actingAs($this->referenteUser)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicators[0]->id,
            'centerId' => $this->center->id,
        ])->assertStatus(200);
    }

    /** @test */
    public function monthly_data_entry_allows_access_when_no_config_record_exists(): void
    {
        // Sin registro en met_indicator_configurations: aplica = null → debe dejar pasar
        $this->createDataFieldsForIndicator($this->indicators[0]);
        $this->seedCompleteFieldSsConfigsForIndicator($this->indicators[0]);

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referenteUser->id,
        ]));

        $this->actingAs($this->referenteUser)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MonthlyDataEntryComponent::class, [
            'indicatorId' => $this->indicators[0]->id,
            'centerId' => $this->center->id,
        ])->assertStatus(200);
    }

    /** @test */
    public function show_controller_blocks_visualizador(): void
    {
        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => false,
        ]));

        $this->createDataFieldsForIndicator($this->indicators[0]);

        $visualizadorRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $visualizador = User::factory()->create(['role_id' => $visualizadorRole->id, 'cen_hie_dependency_id' => 1]);
        $visualizador->role()->associate($visualizadorRole)->save();

        $this->actingAs($visualizador)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.show', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicators[0]->id,
            'center' => $this->center->id,
        ]))->assertForbidden();
    }

    /** @test */
    public function show_controller_blocks_access_when_aplica_false(): void
    {
        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => false,
            'seleccionado' => false,
        ]));

        $this->actingAs($this->referenteUser)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.show', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicators[0]->id,
            'center' => $this->center->id,
        ]))->assertStatus(403);
    }

    /** @test */
    public function show_controller_allows_control_gestion_when_aplica_false(): void
    {
        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => false,
            'seleccionado' => false,
        ]));

        $this->createDataFieldsForIndicator($this->indicators[0]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.show', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicators[0]->id,
            'center' => $this->center->id,
        ]))->assertOk();
    }

    /** @test */
    public function show_controller_allows_admin_when_aplica_false(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $admin->role()->associate($adminRole)->save();

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => false,
            'seleccionado' => false,
        ]));

        $this->createDataFieldsForIndicator($this->indicators[0]);

        $this->actingAs($admin)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.show', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicators[0]->id,
            'center' => $this->center->id,
        ]))->assertOk();
    }

    /** @test */
    public function show_controller_allows_control_gestion_when_center_not_included_in_measurement(): void
    {
        $centerSinInclusion = $this->createCenter('CEN-Z', 'Hospital Z');
        $this->createDataFieldsForIndicator($this->indicators[0]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.show', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicators[0]->id,
            'center' => $centerSinInclusion->id,
        ]))->assertOk();
    }

    /** @test */
    public function show_controller_allows_access_when_aplica_true(): void
    {
        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'aplica' => true,
            'seleccionado' => true,
        ]));

        MetIndicatorReferente::unguarded(fn () => MetIndicatorReferente::create([
            'met_indicator_id' => $this->indicators[0]->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'user_id' => $this->referenteUser->id,
        ]));

        $this->actingAs($this->referenteUser)->withSession(['indicator_year' => $this->year]);

        $this->get(route('metas.instrument.indicador.show', [
            'indicatorType' => $this->indicatorType,
            'indicator' => $this->indicators[0]->id,
            'center' => $this->center->id,
        ]))->assertStatus(200);
    }

    /** @test */
    public function save_config_blocked_when_etapa_cerrado(): void
    {
        $this->setting->etapa_vigente = 'CERRADO';
        $this->setting->save();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $configs = $this->buildConfigs(aplica: true);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('openConfigModal', $this->center->id, $this->center->nombre_corto)
            ->set('configs', $configs)
            ->call('saveConfig')
            ->assertStatus(403);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Construye configs para 5 indicadores con ponderacion 20 cada uno.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildConfigs(bool $aplica): array
    {
        $configs = [];
        foreach ($this->indicators as $indicator) {
            $configs[$indicator->id] = [
                'aplica' => $aplica,
                'seleccionado' => $aplica,
                'ponderacion' => '20',
                'meta_efectiva' => '',
            ];
        }

        return $configs;
    }

    private function seedCompleteFieldSsConfigsForIndicator(MetIndicator $indicator): void
    {
        $fields = MetDataField::query()
            ->where('met_indicator_id', $indicator->id)
            ->get();

        foreach ($fields as $field) {
            MetFieldSsConfig::unguarded(fn () => MetFieldSsConfig::create([
                'met_data_field_id' => $field->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'input_type' => $field->input_type,
                'aggregation_type' => $field->input_type === 'linea_base' ? null : 'sumatoria',
            ]));
        }
    }

    private function createDataFieldsForIndicator(MetIndicator $indicator): void
    {
        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $indicator->id,
            'expression' => [['type' => 'var', 'key' => 'A']],
            'description' => 'Fórmula test',
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'orden' => 1,
        ]));
    }

    private function createCenter(string $codigo, string $nombre, int $depId = 1): CenCenter
    {
        return CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => $codigo,
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => $depId,
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
