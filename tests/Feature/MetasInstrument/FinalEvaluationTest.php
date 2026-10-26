<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Jobs\MetCalculateResultsJob;
use App\Livewire\MetasInstrument\FinalEvaluationComponent;
use App\Livewire\MetasInstrument\InstrumentSettingsComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterInclusion;
use App\Models\MetDataField;
use App\Models\MetFinalEvaluation;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorAppeal;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetPreliminaryEvaluation;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class FinalEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

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

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'FINAL',
            'habilitado' => 1,
        ]));

        $this->createCenterCatalogDependencies();
        $this->center = CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'CEN-FIN',
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => 'Hospital Final',
            'nombre_corto' => 'HospFin',
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
            'clave' => 'MET-FIN-01',
            'nombre' => 'Indicador final',
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
            'ponderacion' => 100,
            'meta_efectiva' => 50,
        ]));
    }

    /** @test */
    public function tab_final_muestra_acciones_explicitas_y_guardar_y_recalcular(): void
    {
        $this->createPreliminaryEvaluation(80, 100);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(FinalEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertSee('Rellenar desde evaluación preliminar')
            ->assertSee('Limpiar todos los datos')
            ->assertSee('Guardar y recalcular')
            ->assertDontSee('Guardar todo')
            ->assertDontSee('Recalcular resultados finales');
    }

    /** @test */
    public function montaje_final_no_rellena_silenciosamente_desde_preliminar(): void
    {
        $this->createPreliminaryEvaluation(80, 100);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(FinalEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertSet('evaluations.'.$this->indicator->id.'.numerador', '')
            ->assertSet('evaluations.'.$this->indicator->id.'.denominador', '');

        $this->assertDatabaseCount('met_final_evaluations', 0);
    }

    /** @test */
    public function rellenar_desde_preliminar_sobrescribe_la_ui_sin_persistir(): void
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

        $this->createPreliminaryEvaluation(40, 50);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(FinalEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('fillFromPreliminary')
            ->assertSet('evaluations.'.$this->indicator->id.'.numerador', '40.00')
            ->assertSet('evaluations.'.$this->indicator->id.'.denominador', '50.00')
            ->assertSee('80.00')
            ->assertDontSee('105.00');

        $this->assertDatabaseCount('met_final_evaluations', 0);
    }

    /** @test */
    public function limpiar_final_solo_modifica_la_ui_hasta_guardar(): void
    {
        MetFinalEvaluation::unguarded(fn () => MetFinalEvaluation::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'numerador' => 80,
            'denominador' => 100,
            'created_by' => $this->controlGestion->id,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(FinalEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('clearAllData')
            ->assertSet('evaluations.'.$this->indicator->id.'.numerador', '')
            ->assertSet('evaluations.'.$this->indicator->id.'.denominador', '');

        $this->assertDatabaseHas('met_final_evaluations', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'numerador' => '80.0000',
            'denominador' => '100.0000',
        ]);
    }

    /** @test */
    public function transicion_a_final_no_precarga_evaluaciones(): void
    {
        $this->createPreliminaryEvaluation(80, 100);
        IndicatorTypeSetting::query()->update(['etapa_vigente' => 'PRELIMINAR']);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(InstrumentSettingsComponent::class)
            ->set('etapaVigente', 'FINAL')
            ->call('saveEtapa')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('met_final_evaluations', 0);
    }

    /** @test */
    public function requiere_acogida_antes_de_guardar_cuando_hay_apelacion(): void
    {
        $this->createPreliminaryEvaluation(80, 100);
        $this->createAppeal();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(FinalEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->call('save')
            ->assertHasErrors(['evaluations.'.$this->indicator->id.'.apelacion_acogida']);
    }

    /** @test */
    public function sin_apelacion_guarda_valores_preliminares_aunque_se_intente_modificar_captura(): void
    {
        $this->createPreliminaryEvaluation(80, 100);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(FinalEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('evaluations.'.$this->indicator->id.'.numerador', '99')
            ->set('evaluations.'.$this->indicator->id.'.denominador', '99')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_final_evaluations', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'numerador' => '80.0000',
            'denominador' => '100.0000',
        ]);
    }

    /** @test */
    public function apelacion_no_acogida_persiste_valores_preliminares(): void
    {
        $this->createPreliminaryEvaluation(80, 100);
        $this->createAppeal();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(FinalEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('evaluations.'.$this->indicator->id.'.apelacion_acogida', '0')
            ->set('evaluations.'.$this->indicator->id.'.numerador', '99')
            ->set('evaluations.'.$this->indicator->id.'.denominador', '99')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_final_evaluations', [
            'met_indicator_id' => $this->indicator->id,
            'apelacion_acogida' => 0,
            'numerador' => '80.0000',
            'denominador' => '100.0000',
        ]);
    }

    /** @test */
    public function control_gestion_guarda_evaluacion_final_con_resolucion_apelacion_acogida(): void
    {
        $this->createPreliminaryEvaluation(80, 100);
        $this->createAppeal();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(FinalEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->set('evaluations.'.$this->indicator->id.'.apelacion_acogida', '1')
            ->set('evaluations.'.$this->indicator->id.'.numerador', '90')
            ->set('evaluations.'.$this->indicator->id.'.denominador', '100')
            ->set('evaluations.'.$this->indicator->id.'.resolucion_notas', 'Apelación acogida en prueba.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_final_evaluations', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'apelacion_acogida' => 1,
            'numerador' => '90.0000',
        ]);

        $this->assertSame(1, MetFinalEvaluation::query()->count());
    }

    /** @test */
    public function notas_de_resolucion_se_editan_en_modal_y_se_persisten_al_guardar_evaluacion_final(): void
    {
        $this->createPreliminaryEvaluation(80, 100);
        $this->createAppeal();

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(FinalEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertSee('Notas')
            ->call('openResolutionNotesModal', $this->indicator->id)
            ->assertSet('showResolutionNotesModal', true)
            ->assertSee('Notas de resolución')
            ->set('resolutionNotesDraft', 'Resolución registrada desde el modal.')
            ->call('applyResolutionNotes')
            ->assertSet('showResolutionNotesModal', false)
            ->assertSet('evaluations.'.$this->indicator->id.'.resolucion_notas', 'Resolución registrada desde el modal.')
            ->set('evaluations.'.$this->indicator->id.'.apelacion_acogida', '1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('met_final_evaluations', [
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'resolucion_notas' => 'Resolución registrada desde el modal.',
        ]);
    }

    /** @test */
    public function recalcular_dispara_job_con_etapa_final(): void
    {
        Bus::fake();

        $this->createPreliminaryEvaluation(80, 100);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(FinalEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])->call('recalculate');

        Bus::assertDispatched(MetCalculateResultsJob::class, function (MetCalculateResultsJob $job) {
            return $job->etapa === 'FINAL';
        });
    }

    /** @test */
    public function referente_no_ve_notas_ni_alerta_de_edicion(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create(['role_id' => $referenteRole->id, 'cen_hie_dependency_id' => 1]);
        $referente->role()->associate($referenteRole)->save();

        $this->actingAs($referente)->withSession(['indicator_year' => $this->year]);

        Livewire::test(FinalEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertDontSee('Rellenar desde evaluación preliminar')
            ->assertDontSee('solo puede editarse cuando la etapa vigente')
            ->assertDontSee('Limpiar todos los datos');
    }

    /** @test */
    public function control_gestion_ve_alerta_si_etapa_no_es_final(): void
    {
        IndicatorTypeSetting::query()
            ->where('indicator_type_id', $this->indicatorType->id)
            ->where('year_id', $this->year->id)
            ->update(['etapa_vigente' => 'PRELIMINAR']);

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(FinalEvaluationComponent::class, [
            'indicatorTypeId' => $this->indicatorType->id,
            'centerId' => $this->center->id,
        ])
            ->assertSee('Rellenar desde evaluación preliminar')
            ->assertSee('solo puede editarse cuando la etapa vigente')
            ->assertSee('FINAL');
    }

    private function createPreliminaryEvaluation(float $numerador, float $denominador): void
    {
        MetPreliminaryEvaluation::unguarded(fn () => MetPreliminaryEvaluation::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'numerador' => $numerador,
            'denominador' => $denominador,
            'created_by' => $this->controlGestion->id,
        ]));
    }

    private function createAppeal(): void
    {
        MetIndicatorAppeal::unguarded(fn () => MetIndicatorAppeal::create([
            'met_indicator_id' => $this->indicator->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'appeal_type' => 'CAUSA_EXTERNA',
            'motivo' => 'Motivo de prueba con más de diez caracteres.',
            'created_by' => $this->controlGestion->id,
        ]));
    }

    private function createCenterCatalogDependencies(): void {}
}
