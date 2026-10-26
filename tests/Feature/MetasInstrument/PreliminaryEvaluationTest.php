<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Jobs\MetCalculateResultsJob;
use App\Livewire\MetasInstrument\InstrumentSettingsComponent;
use App\Livewire\MetasInstrument\PreliminaryEvaluationComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetDataField;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetPreliminaryEvaluation;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class PreliminaryEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private IndicatorTypeSetting $setting;

    private User $controlGestion;

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

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->setting = IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'PRELIMINAR',
            'habilitado' => 1,
        ]));

        $this->createCenterCatalogDependencies();
        $this->center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-PRE',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Preliminar',
            'nombre_corto' => 'HospPre',
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

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->indicator = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'MET-PRE-01',
            'nombre' => 'Indicador preliminar',
            'decimales' => 2,
            'meta_valor' => 50,
            'meta_operador' => '>=',
            'year_id' => $this->year->id,
            'estado' => true,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'orden' => 0,
        ]));

        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Denominador',
            'variable_key' => 'B',
            'componente' => 'denominador',
            'input_type' => 'mensual',
            'orden' => 1,
        ]));

        MetFormula::unguarded(fn () => MetFormula::create([
            'met_indicator_id' => $this->indicator->id,
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
            'description' => '(A / B) * 100',
        ]));

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $this->indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'seleccionado' => true,
            'aplica' => true,
            'ponderacion' => 15,
            'meta_efectiva' => 50,
        ]));
    }

    /** @test */
    public function muestra_resultado_cumplimiento_y_ponderado_en_tabla(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('evaluations.'.$this->indicator->id.'.numerador', '40')
            ->set('evaluations.'.$this->indicator->id.'.denominador', '50')
            ->assertSee('80.00')
            ->assertSee('100.00%')
            ->assertSee('15.00%')
            ->assertSee('Cumplimiento global')
            ->assertSee('Cumplimiento satisfactorio');
    }

    /** @test */
    public function resultado_usa_cuociente_simple_no_formula_del_indicador(): void
    {
        MetFormula::query()->where('met_indicator_id', $this->indicator->id)->update([
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
                ['type' => 'op', 'value' => '+'],
                ['type' => 'lit', 'value' => '25'],
            ],
            'description' => '(A / B) * 100 + 25',
        ]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        // Fórmula daría 105; cuociente simple (40/50)×100 = 80.
        Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('evaluations.'.$this->indicator->id.'.numerador', '40')
            ->set('evaluations.'.$this->indicator->id.'.denominador', '50')
            ->assertSee('80.00')
            ->assertDontSee('105.00');
    }

    /** @test */
    public function semaforo_cumplimiento_usa_umbral_superior_del_indicador(): void
    {
        $this->indicator->update(['umbral_superior' => 95, 'umbral_inferior' => 60, 'meta_valor' => 100]);
        MetIndicatorConfiguration::query()
            ->where('met_indicator_id', $this->indicator->id)
            ->where('cen_center_id', $this->center->id)
            ->update(['meta_efectiva' => 100]);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ]);

        $component
            ->set('evaluations.'.$this->indicator->id.'.numerador', '95')
            ->set('evaluations.'.$this->indicator->id.'.denominador', '100')
            ->assertSeeHtml('<span class="label label-success">'."\n".'                                        95.00%');

        $component
            ->set('evaluations.'.$this->indicator->id.'.numerador', '94')
            ->assertSeeHtml('<span class="label label-warning">'."\n".'                                        94.00%');

        $component
            ->set('evaluations.'.$this->indicator->id.'.numerador', '70')
            ->assertSeeHtml('<span class="label label-warning">'."\n".'                                        70.00%');

        $component
            ->set('evaluations.'.$this->indicator->id.'.numerador', '50')
            ->assertSeeHtml('<span class="label label-danger">'."\n".'                                        50.00%');
    }

    /** @test */
    public function tab_preliminar_muestra_acciones_explicitas_y_guardar_y_recalcular(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertSee('Rellenar desde autoevaluación')
            ->assertSee('Limpiar todos los datos')
            ->assertSee('Guardar y recalcular')
            ->assertDontSee('Guardar todo')
            ->assertDontSee('Recalcular resultados');
    }

    /** @test */
    public function control_gestion_guarda_evaluacion_preliminar(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('evaluations.'.$this->indicator->id.'.numerador', '80')
            ->set('evaluations.'.$this->indicator->id.'.denominador', '100')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_preliminary_evaluations', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'numerador' => '80.0000',
            'denominador' => '100.0000',
        ]);
    }

    /** @test */
    public function recalcular_dispara_job_con_etapa_preliminar(): void
    {
        Bus::fake();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])->call('recalculate');

        Bus::assertDispatched(MetCalculateResultsJob::class, function (MetCalculateResultsJob $job) {
            return $job->etapa === 'PRELIMINAR';
        });
    }

    /** @test */
    public function rellenar_desde_autoevaluacion_sobrescribe_las_celdas_sin_persistir(): void
    {
        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 70,
            'denominador' => 100,
            'resultado' => 70,
            'cumple_meta' => true,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('evaluations.'.$this->indicator->id.'.numerador', '1')
            ->set('evaluations.'.$this->indicator->id.'.denominador', '2')
            ->call('fillFromAutoevaluacion')
            ->assertSet('evaluations.'.$this->indicator->id.'.numerador', '70.00')
            ->assertSet('evaluations.'.$this->indicator->id.'.denominador', '100.00');

        $this->assertDatabaseCount('met_preliminary_evaluations', 0);
    }

    /** @test */
    public function rellenar_desde_autoevaluacion_usa_par_equivalente_para_formula_compleja(): void
    {
        MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $this->indicator->id,
            'label' => 'Numerador complementario',
            'variable_key' => 'C',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'orden' => 2,
        ]));
        MetFormula::query()->where('met_indicator_id', $this->indicator->id)->update([
            'expression' => [
                ['type' => 'var', 'key' => 'A'],
                ['type' => 'op', 'value' => '-'],
                ['type' => 'var', 'key' => 'C'],
                ['type' => 'op', 'value' => '/'],
                ['type' => 'var', 'key' => 'B'],
                ['type' => 'op', 'value' => '*'],
                ['type' => 'lit', 'value' => '100'],
            ],
            'description' => '(A - C) / B * 100',
        ]);
        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 999,
            'denominador' => 10,
            'resultado' => 12.34,
            'cumple_meta' => false,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('fillFromAutoevaluacion')
            ->assertSet('evaluations.'.$this->indicator->id.'.numerador', '12.34')
            ->assertSet('evaluations.'.$this->indicator->id.'.denominador', '100.00')
            ->assertSee('12.34');
    }

    /** @test */
    public function limpiar_solo_persiste_cuando_se_guarda_y_no_se_rellena_al_remontar(): void
    {
        MetPreliminaryEvaluation::unguarded(fn () => MetPreliminaryEvaluation::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'numerador' => 70,
            'denominador' => 100,
            'created_by' => $this->controlGestion->id,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('clearAllData')
            ->assertSet('evaluations.'.$this->indicator->id.'.numerador', '')
            ->assertSet('evaluations.'.$this->indicator->id.'.denominador', '');

        $this->assertDatabaseHas('met_preliminary_evaluations', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'numerador' => '70.0000',
            'denominador' => '100.0000',
        ]);

        Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('clearAllData')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_preliminary_evaluations', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'numerador' => null,
            'denominador' => null,
        ]);

        Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertSet('evaluations.'.$this->indicator->id.'.numerador', '')
            ->assertSet('evaluations.'.$this->indicator->id.'.denominador', '');
    }

    /** @test */
    public function transicion_a_preliminar_no_precarga_evaluaciones(): void
    {
        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'periodo' => 'ANUAL',
            'etapa' => 'AUTOEVALUACION',
            'numerador' => 70,
            'denominador' => 100,
            'resultado' => 70,
            'cumple_meta' => true,
        ]));

        $this->setting->update(['etapa_vigente' => 'AUTOEVALUACION']);
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('etapaVigente', 'PRELIMINAR')
            ->call('saveEtapa')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('met_preliminary_evaluations', 0);
    }

    /** @test */
    public function referente_no_ve_notas_ni_alerta_de_edicion(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $referente->role()->associate($referenteRole)->save();

        $this->actingAs($referente)->withSession(['indicator_year' => $this->year]);

        Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertDontSee('Capture numerador y denominador')
            ->assertDontSee('solo puede editarse cuando la etapa vigente')
            ->assertDontSee('Rellenar desde autoevaluación');
    }

    /** @test */
    public function control_gestion_ve_alerta_si_etapa_no_es_preliminar(): void
    {
        $this->updateMetasEtapa($this->setting, 'AUTOEVALUACION');
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(PreliminaryEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertSee('Capture numerador y denominador')
            ->assertSee('solo puede editarse cuando la etapa vigente')
            ->assertSee('PRELIMINAR');
    }

    private function createCenterCatalogDependencies(): void {}
}
