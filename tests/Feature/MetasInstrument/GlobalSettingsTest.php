<?php

namespace Tests\Feature\MetasInstrument;

use App\Livewire\MetasInstrument\InstrumentSettingsComponent;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeGlobalSetting;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Support\Metas\MetSemaforoThresholds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private User $adminUser;

    private User $parametrizador;

    private User $controlGestion;

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
        $parametrizadorRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);

        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $this->adminUser->role()->associate($adminRole)->save();

        $this->parametrizador = User::factory()->create(['role_id' => $parametrizadorRole->id, 'cen_hie_dependency_id' => 1]);
        $this->parametrizador->role()->associate($parametrizadorRole)->save();

        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));
    }

    /** @test */
    public function get_data_crea_fila_con_defaults_si_no_existe(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->assertDatabaseMissing('indicator_type_global_settings', [
            'indicator_type_id' => $this->indicatorType->id,
        ]);

        $globalSetting = IndicatorTypeGlobalSetting::getData($this->indicatorType->id);

        $this->assertEquals(4, $globalSetting->min_indicadores);
        $this->assertEquals('0.00', $globalSetting->min_ponderacion);
        $this->assertEquals('100.00', $globalSetting->max_ponderacion);
        $this->assertNull($globalSetting->semaforo_verde_min);
        $this->assertNull($globalSetting->semaforo_amarillo_min);

        $this->assertDatabaseHas('indicator_type_global_settings', [
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 4,
        ]);
    }

    /** @test */
    public function get_data_llamado_dos_veces_no_duplica_filas(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        IndicatorTypeGlobalSetting::getData($this->indicatorType->id);
        IndicatorTypeGlobalSetting::getData($this->indicatorType->id);

        $this->assertDatabaseCount('indicator_type_global_settings', 1);
    }

    /** @test */
    public function parametrizador_puede_ver_panel_parametros_en_instrument_settings(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->assertStatus(200)
            ->assertSet('minIndicadores', 4)
            ->assertSee('Parámetros de convenio');
    }

    /** @test */
    public function parametrizador_guarda_parametros_validos(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('minIndicadores', 5)
            ->set('minPonderacion', '10.00')
            ->set('maxPonderacion', '40.00')
            ->call('saveParams')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('indicator_type_global_settings', [
            'indicator_type_id' => $this->indicatorType->id,
            'min_indicadores' => 5,
            'min_ponderacion' => 10.00,
            'max_ponderacion' => 40.00,
        ]);
    }

    /** @test */
    public function guarda_min_indicadores_cero_falla_validacion(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('minIndicadores', 0)
            ->set('minPonderacion', '0.00')
            ->set('maxPonderacion', '100.00')
            ->call('saveParams')
            ->assertHasErrors(['minIndicadores']);
    }

    /** @test */
    public function guarda_min_ponderacion_mayor_o_igual_que_max_falla_validacion(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('minIndicadores', 4)
            ->set('minPonderacion', '50.00')
            ->set('maxPonderacion', '50.00')
            ->call('saveParams')
            ->assertHasErrors(['minPonderacion']);
    }

    /** @test */
    public function guarda_parametros_inviables_4_por_20_menor_100_falla(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('minIndicadores', 4)
            ->set('minPonderacion', '0.00')
            ->set('maxPonderacion', '20.00')
            ->call('saveParams')
            ->assertHasErrors(['minIndicadores']);
    }

    /** @test */
    public function control_gestion_no_puede_guardar_parametros(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('minIndicadores', 5)
            ->set('minPonderacion', '0.00')
            ->set('maxPonderacion', '100.00')
            ->call('saveParams')
            ->assertStatus(403);
    }

    /** @test */
    public function admin_puede_guardar_parametros(): void
    {
        $this->actingAs($this->adminUser)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('minIndicadores', 3)
            ->set('minPonderacion', '0.00')
            ->set('maxPonderacion', '50.00')
            ->call('saveParams')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('indicator_type_global_settings', [
            'indicator_type_id' => $this->indicatorType->id,
            'min_indicadores' => 3,
        ]);
    }

    /** @test */
    public function resolve_for_no_inserta_fila(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $this->assertDatabaseCount('indicator_type_global_settings', 0);

        $found = IndicatorTypeGlobalSetting::resolveFor($this->indicatorType->id, $this->year->id);

        $this->assertNull($found);
        $this->assertDatabaseCount('indicator_type_global_settings', 0);
    }

    /** @test */
    public function for_indicator_type_sin_fila_equivale_a_defaults_por_ruta(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        foreach (['metas-18834', 'metas-19664', 'metas-20707'] as $route) {
            $type = $this->indicatorTypeByRoute($route, ['estado' => true]);
            $resolved = MetSemaforoThresholds::forIndicatorType($type->id, $route, $this->year->id);
            $defaults = MetSemaforoThresholds::defaultsForRoute($route);

            $this->assertSame($defaults->greenMinPercent, $resolved->greenMinPercent, $route);
            $this->assertSame($defaults->yellowMinPercent, $resolved->yellowMinPercent, $route);
        }

        $this->assertDatabaseCount('indicator_type_global_settings', 0);
    }

    /** @test */
    public function override_por_ano_no_recolorea_ano_anterior(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $type = $this->indicatorTypeByRoute('metas-19664', ['estado' => true]);
        $yearAnterior = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));

        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 4,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
            'semaforo_verde_min' => 90,
            'semaforo_amarillo_min' => 70,
        ]));

        $actual = MetSemaforoThresholds::forIndicatorType($type->id, $type->route, $this->year->id);
        $anterior = MetSemaforoThresholds::forIndicatorType($type->id, $type->route, $yearAnterior->id);

        $this->assertSame(90.0, $actual->greenMinPercent);
        $this->assertSame(70.0, $actual->yellowMinPercent);
        $this->assertSame('warning', $actual->labelClass(72.0));
        $this->assertSame(95.0, $anterior->greenMinPercent);
        $this->assertSame(75.0, $anterior->yellowMinPercent);
        $this->assertSame('danger', $anterior->labelClass(72.0));
    }

    /** @test */
    public function override_parcial_amarillo_hereda_default(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        $type = $this->indicatorTypeByRoute('metas-19664', ['estado' => true]);

        IndicatorTypeGlobalSetting::unguarded(fn () => IndicatorTypeGlobalSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $this->year->id,
            'min_indicadores' => 4,
            'min_ponderacion' => 0,
            'max_ponderacion' => 100,
            'semaforo_verde_min' => 90,
            'semaforo_amarillo_min' => null,
        ]));

        $resolved = MetSemaforoThresholds::forIndicatorType($type->id, $type->route, $this->year->id);

        $this->assertSame(90.0, $resolved->greenMinPercent);
        $this->assertSame(75.0, $resolved->yellowMinPercent);
    }

    /** @test */
    public function parametrizador_ve_seccion_semaforo_global(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->assertStatus(200)
            ->assertSee('Umbrales de semáforo global')
            ->assertSee('Valor heredado por ley del instrumento')
            ->assertSee('Cumplimiento global satisfactorio')
            ->assertSee('Guardar umbrales');
    }

    /** @test */
    public function parametrizador_guarda_umbrales_semaforo(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('semaforoVerdeMin', '90.00')
            ->set('semaforoAmarilloMin', '70.00')
            ->call('saveSemaforo')
            ->assertHasNoErrors()
            ->assertSet('semaforoVerdeEsHeredado', false)
            ->assertSet('semaforoAmarilloEsHeredado', false);

        $this->assertDatabaseHas('indicator_type_global_settings', [
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'semaforo_verde_min' => 90.00,
            'semaforo_amarillo_min' => 70.00,
            'min_indicadores' => 4,
        ]);
    }

    /** @test */
    public function guarda_semaforo_amarillo_mayor_o_igual_que_verde_falla_validacion(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('semaforoVerdeMin', '70.00')
            ->set('semaforoAmarilloMin', '90.00')
            ->call('saveSemaforo')
            ->assertHasErrors(['semaforoAmarilloMin']);

        $this->assertDatabaseHas('indicator_type_global_settings', [
            'indicator_type_id' => $this->indicatorType->id,
        ]);
        $setting = IndicatorTypeGlobalSetting::query()
            ->where('indicator_type_id', $this->indicatorType->id)
            ->first();
        $this->assertNull($setting?->semaforo_verde_min);
        $this->assertNull($setting?->semaforo_amarillo_min);
    }

    /** @test */
    public function control_gestion_ve_parametros_y_umbrales_en_solo_lectura(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->assertStatus(200)
            ->assertSet('canEditGlobalParams', false)
            ->assertSee('Parámetros de convenio')
            ->assertSee('Umbrales de semáforo global')
            ->assertSee('Solo lectura. Los edita el rol parametrizador.')
            ->assertSee('Cumplimiento global satisfactorio')
            ->assertDontSee('Guardar parámetros')
            ->assertDontSee('Guardar umbrales');
    }

    /** @test */
    public function control_gestion_no_puede_guardar_semaforo(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('semaforoVerdeMin', '90.00')
            ->set('semaforoAmarilloMin', '70.00')
            ->call('saveSemaforo')
            ->assertStatus(403);
    }
}
