<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Jobs\MetCalculateResultsJob;
use App\Livewire\MetasInstrument\InstrumentSettingsComponent;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetStageTransition;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class InstrumentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $adminUser;

    private User $parametrizador;

    private User $controlGestion;

    private IndicatorType $indicatorType;

    private IndicatorTypeSetting $setting;

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

        $this->setting = IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'CERRADO',
            'habilitado' => 1,
        ]));
    }

    /** @test */
    public function parametrizador_can_render_instrument_settings_component(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->assertStatus(200)
            ->assertSet('etapaVigente', 'CERRADO');
    }

    /** @test */
    public function admin_can_render_instrument_settings_component(): void
    {
        $this->actingAs($this->adminUser)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->assertStatus(200)
            ->assertSet('habilitado', true);
    }

    /** @test */
    public function control_gestion_can_access_instrument_settings(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->assertStatus(200)
            ->assertSet('habilitado', true);
    }

    /** @test */
    public function control_gestion_can_change_etapa_vigente(): void
    {
        $this->setting->update(['etapa_vigente' => EtapaVigente::NoIniciado->value, 'habilitado' => false]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('etapaVigente', 'FORMULACION')
            ->call('saveEtapa')
            ->assertHasNoErrors()
            ->assertSet('etapaVigente', 'FORMULACION');

        $this->assertDatabaseHas('indicator_type_settings', [
            'id' => $this->setting->id,
            'etapa_vigente' => 'FORMULACION',
        ]);
    }

    /** @test */
    public function parametrizador_cannot_change_etapa_vigente(): void
    {
        $this->actingAs($this->parametrizador)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('etapaVigente', 'AUTOEVALUACION')
            ->call('saveEtapa')
            ->assertStatus(403);
    }

    /** @test */
    public function transicionar_a_cerrado_no_despacha_job_de_autoevaluacion(): void
    {
        Bus::fake();

        $this->setting->update(['etapa_vigente' => 'AUTOEVALUACION']);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('etapaVigente', 'CERRADO')
            ->call('saveEtapa')
            ->assertHasNoErrors();

        Bus::assertNotDispatched(MetCalculateResultsJob::class);
    }

    /** @test */
    public function save_etapa_rejects_invalid_value(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('etapaVigente', 'INVALIDO')
            ->call('saveEtapa')
            ->assertHasErrors(['etapaVigente']);
    }

    /** @test */
    public function save_etapa_rejects_legacy_anual_value(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('etapaVigente', 'ANUAL')
            ->call('saveEtapa')
            ->assertHasErrors(['etapaVigente']);
    }

    /** @test */
    public function avanzar_a_formulacion_deriva_habilitado_y_registra_transicion(): void
    {
        IndicatorTypeSetting::unguarded(fn () => $this->setting->update([
            'etapa_vigente' => EtapaVigente::NoIniciado->value,
            'habilitado' => false,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('etapaVigente', EtapaVigente::Formulacion->value)
            ->call('saveEtapa')
            ->assertHasNoErrors()
            ->assertSet('habilitado', true);

        $this->assertDatabaseHas('indicator_type_settings', [
            'id' => $this->setting->id,
            'etapa_vigente' => EtapaVigente::Formulacion->value,
            'habilitado' => 1,
        ]);

        $this->assertDatabaseHas('met_stage_transitions', [
            'indicator_type_id' => $this->indicatorType->id,
            'etapa_desde' => EtapaVigente::NoIniciado->value,
            'etapa_hasta' => EtapaVigente::Formulacion->value,
        ]);
    }

    /** @test */
    public function retroceder_etapa_sin_motivo_falla_validacion(): void
    {
        $this->setting->update(['etapa_vigente' => EtapaVigente::Autoevaluacion->value]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('etapaVigente', EtapaVigente::Formulacion->value)
            ->call('saveEtapa')
            ->assertHasErrors(['motivoRetroceso'])
            ->assertSee('No se pudo actualizar la etapa');

        $this->assertDatabaseHas('indicator_type_settings', [
            'id' => $this->setting->id,
            'etapa_vigente' => EtapaVigente::Autoevaluacion->value,
        ]);
    }

    /** @test */
    public function retroceder_etapa_con_motivo_registra_transicion(): void
    {
        $this->setting->update(['etapa_vigente' => EtapaVigente::Autoevaluacion->value]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('etapaVigente', EtapaVigente::Formulacion->value)
            ->set('motivoRetroceso', 'Convenio incompleto detectado en auditoría')
            ->call('saveEtapa')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_stage_transitions', [
            'indicator_type_id' => $this->indicatorType->id,
            'etapa_desde' => EtapaVigente::Autoevaluacion->value,
            'etapa_hasta' => EtapaVigente::Formulacion->value,
            'motivo' => 'Convenio incompleto detectado en auditoría',
        ]);
    }

    /** @test */
    public function historial_pagina_cuando_supera_el_tamano_de_pagina(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        for ($i = 0; $i < 11; $i++) {
            MetStageTransition::query()->create([
                'indicator_type_id' => $this->indicatorType->id,
                'cen_hie_dependency_id' => 1,
                'year_id' => $this->year->id,
                'etapa_desde' => EtapaVigente::Autoevaluacion,
                'etapa_hasta' => EtapaVigente::Formulacion,
                'motivo' => 'Prueba paginación '.$i,
                'user_id' => $this->controlGestion->id,
                'created_at' => now()->subMinutes(11 - $i),
            ]);
        }

        Livewire::test(InstrumentSettingsComponent::class)
            ->call('openStageHistoryModal')
            ->assertSee('Historial de cambios de etapa')
            ->assertSee('Mostrando')
            ->assertSeeHtml('metas-bitacora-pagination');
    }

    /** @test */
    public function historial_de_etapas_se_abre_en_modal_y_carga_transiciones(): void
    {
        $this->setting->update(['etapa_vigente' => EtapaVigente::NoIniciado->value, 'habilitado' => false]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('etapaVigente', EtapaVigente::Formulacion->value)
            ->call('saveEtapa')
            ->assertSet('showStageHistoryModal', false)
            ->call('openStageHistoryModal')
            ->assertSet('showStageHistoryModal', true)
            ->assertSee('Historial de cambios de etapa')
            ->assertSee('No iniciado')
            ->assertSee('Formulación')
            ->call('closeStageHistoryModal')
            ->assertSet('showStageHistoryModal', false);
    }

    /** @test */
    public function recalculate_now_muestra_aviso_cuando_hay_trabajos_en_cola(): void
    {
        $this->setting->update(['etapa_vigente' => EtapaVigente::Autoevaluacion->value]);

        Queue::connection('database')->push(new MetCalculateResultsJob(
            $this->year->id,
            1,
            $this->indicatorType->id,
            EtapaVigente::Autoevaluacion->value,
        ));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertSee('Recalcular resultados')
            ->assertSee('Existen 1 proceso de cálculo en cola que se ejecutarán en los próximos 2 minutos.');
    }

    /** @test */
    public function recalculate_now_despacha_job_manual(): void
    {
        Bus::fake();

        $this->setting->update(['etapa_vigente' => EtapaVigente::Autoevaluacion->value]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->call('recalculateNow')
            ->assertHasNoErrors();

        Bus::assertDispatched(MetCalculateResultsJob::class, function (MetCalculateResultsJob $job) {
            return $job->yearId === $this->year->id
                && $job->dependencyId === 1
                && $job->etapa === EtapaVigente::Autoevaluacion->value;
        });
    }

    /** @test */
    public function recalculate_now_no_encola_job_duplicado_en_cooldown(): void
    {
        Bus::fake();

        $this->setting->update(['etapa_vigente' => EtapaVigente::Autoevaluacion->value]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->call('recalculateNow')
            ->call('recalculateNow')
            ->assertHasNoErrors();

        Bus::assertDispatchedTimes(MetCalculateResultsJob::class, 1);
    }

    /** @test */
    public function recalculate_now_en_etapa_preliminar_despacha_job_de_etapa(): void
    {
        Bus::fake();

        $this->setting->update(['etapa_vigente' => EtapaVigente::Preliminar->value]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->call('recalculateNow')
            ->assertHasNoErrors()
            ->assertSee('Recálculo de resultados enviado a cola (Evaluación preliminar).');

        Bus::assertDispatched(MetCalculateResultsJob::class, function (MetCalculateResultsJob $job) {
            return $job->etapa === EtapaVigente::Preliminar->value;
        });
    }

    /** @test */
    public function recalculate_now_en_etapa_apelacion_muestra_mensaje_sin_encolar(): void
    {
        Bus::fake();

        $this->setting->update(['etapa_vigente' => EtapaVigente::Apelacion->value]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->call('recalculateNow')
            ->assertHasNoErrors()
            ->assertSee('El recálculo manual no está disponible en la etapa vigente: Apelación.');

        Bus::assertNotDispatched(MetCalculateResultsJob::class);
    }

    /** @test */
    public function recalculate_now_usa_etapa_persistida_aunque_el_select_tenga_otro_valor(): void
    {
        Bus::fake();

        $this->setting->update(['etapa_vigente' => EtapaVigente::Preliminar->value]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('etapaVigente', EtapaVigente::Autoevaluacion->value)
            ->call('recalculateNow')
            ->assertHasNoErrors();

        Bus::assertDispatched(MetCalculateResultsJob::class, function (MetCalculateResultsJob $job) {
            return $job->etapa === EtapaVigente::Preliminar->value;
        });
    }
}
