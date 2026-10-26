<?php

namespace Tests\Feature\Comges;

use App\Enums\IndicatorType\EtapaVigente;
use App\Livewire\SearchCommitmentComponent;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class ComgesCommitmentBrowseAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $referente;

    private int $hieId;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerStubRoutes();

        $this->hieId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-ALERT',
            'nombre' => 'Servicio Alertas',
            'tipo_dependencia' => 'SERVICIO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $yearId = (int) DB::table('years')->insertGetId([
            'ano' => 2025,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->year = Year::findOrFail($yearId);

        $role = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $this->referente = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $this->hieId,
        ]);
        $this->referente->role()->associate($role)->save();
    }

    public function test_referente_sees_pending_verification_alert(): void
    {
        $this->seedCommitmentWithEmptyVerifiableDocument();

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year]);

        IndicatorTypeSetting::query()->updateOrCreate(
            [
                'indicator_type_id' => 1,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => $this->hieId,
            ],
            [
                'etapa_vigente' => EtapaVigente::Autoevaluacion,
                'habilitado' => true,
            ]
        );

        Livewire::test(SearchCommitmentComponent::class)
            ->assertSee('data-centinela-visual-alert-tooltip', false)
            ->assertSee('data-toggle="tooltip"', false)
            ->assertSee('aria-describedby=', false)
            ->assertSee('Pendiente medios de verificación', false)
            ->assertSee('centinela-visual-alert-icon', false)
            ->assertSee('centinela-semaforo-with-alerts', false);
    }

    public function test_r7_hidden_when_etapa_is_autoevaluacion(): void
    {
        $this->seedCommitmentWithAppealOpportunityScores();

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year]);

        IndicatorTypeSetting::query()->updateOrCreate(
            [
                'indicator_type_id' => 1,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => $this->hieId,
            ],
            [
                'etapa_vigente' => EtapaVigente::Autoevaluacion,
                'habilitado' => true,
            ]
        );

        Livewire::test(SearchCommitmentComponent::class)
            ->assertDontSee('title="¿Oportunidad de apelación?"', false);
    }

    public function test_alerts_hidden_while_searching(): void
    {
        $this->seedCommitmentWithEmptyVerifiableDocument();

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year]);

        IndicatorTypeSetting::query()->updateOrCreate(
            [
                'indicator_type_id' => 1,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => $this->hieId,
            ],
            [
                'etapa_vigente' => EtapaVigente::Autoevaluacion,
                'habilitado' => true,
            ]
        );

        Livewire::test(SearchCommitmentComponent::class)
            ->set('search', 'C-ALERT')
            ->assertViewHas('isSearching', true)
            ->assertDontSee('Pendiente medios de verificación', false)
            ->assertDontSee('centinela-visual-alert-icon', false);
    }

    public function test_r7_visible_when_etapa_is_preliminar(): void
    {
        $this->seedCommitmentWithAppealOpportunityScores();

        $this->actingAs($this->referente)
            ->withSession(['indicator_year' => $this->year]);

        IndicatorTypeSetting::query()->updateOrCreate(
            [
                'indicator_type_id' => 1,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => $this->hieId,
            ],
            [
                'etapa_vigente' => EtapaVigente::Preliminar,
                'habilitado' => true,
            ]
        );

        Livewire::test(SearchCommitmentComponent::class)
            ->assertSee('title="¿Oportunidad de apelación?"', false);
    }

    private function seedCommitmentWithEmptyVerifiableDocument(): void
    {
        $ids = $this->seedBaseStructure();
        $actionId = $ids['action_id'];

        DB::table('com_verifiable_documents')->insert([
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => $this->hieId,
            'archivos' => '[]',
            'descripcion' => 'Sin archivos',
            'verifiable_type' => 'EVALUACION',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedCommitmentWithAppealOpportunityScores(): void
    {
        $ids = $this->seedBaseStructure();
        $actionId = $ids['action_id'];

        DB::table('com_evaluations')->insert([
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => $this->hieId,
            'tipo_evaluacion' => 'AUTOEVALUACION',
            'numerador' => 3.5,
            'denominador' => 4,
            'resultado' => 3.5,
            'cumplimiento' => 3.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('com_evaluations')->insert([
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => $this->hieId,
            'tipo_evaluacion' => 'PRELIMINAR',
            'numerador' => 2.0,
            'denominador' => 4,
            'resultado' => 2.0,
            'cumplimiento' => 2.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{action_id: int}
     */
    private function seedBaseStructure(): array
    {
        if (! DB::table('indicator_types')->where('id', 1)->exists()) {
            DB::table('indicator_types')->insert([
                'id' => 1,
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'route' => 'voyager.compromisos.index',
                'estado' => 1,
                'orden' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $indicatorTypeId = 1;

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1]),
            'nombre_corte' => 'Corte I',
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'C-ALERT',
            'nombre' => 'Compromiso alertas',
            'year_id' => $this->year->id,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'I-ALERT',
            'nombre' => 'Indicador',
            'ponderacion_indicador' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $linkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actionId = (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $linkId,
            'numero' => '1',
            'ponderacion_accion' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['action_id' => $actionId];
    }

    private function registerStubRoutes(): void
    {
        Route::get('/admin/indicadores-comges/{id}', fn () => '')->name('voyager.indicadores-comges.show');
        Route::get('/admin/indicadores-comges/create', fn () => '')->name('voyager.indicadores-comges.create');
        Route::get('/admin/compromisos/{id}/edit', fn () => '')->name('voyager.compromisos.edit');
    }
}
