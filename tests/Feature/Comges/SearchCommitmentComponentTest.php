<?php

namespace Tests\Feature\Comges;

use App\Livewire\SearchCommitmentComponent;
use App\Models\ComIndicator;
use App\Models\IndicatorAmbito;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Indicators\IndicatorAmbitoAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class SearchCommitmentComponentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $cenHieDependencyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerStubRoutes();

        $this->cenHieDependencyId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-LW',
            'nombre' => 'Servicio Livewire',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
        ]);
        $this->user->role()->associate($role)->save();
    }

    public function test_legacy_year_uses_percentage_semaforo(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedCommitmentGraph((int) $year->id, 'C-LEG', 'I-LEG', 80.0);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->assertViewHas('useScoreScale', false)
            ->assertViewHas('isSearching', false)
            ->assertSee('semaforo orange')
            ->assertSee('80%')
            ->assertSee('Promedio país');
    }

    public function test_score_scale_year_uses_puntaje_layout(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedCommitmentGraph((int) $year->id, 'C-2026', 'I-2026', 3.5);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->assertViewHas('useScoreScale', true)
            ->assertViewHas('isSearching', false)
            ->assertSee('commitment-indicators-table')
            ->assertSee('Desempeño global del compromiso en puntaje');
    }

    public function test_score_year_hides_commitment_performance_while_searching(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedCommitmentGraph((int) $year->id, 'C-PERF', 'I-PERF', 3.5);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->set('search', 'C-PERF')
            ->assertViewHas('isSearching', true)
            ->assertDontSee('Desempeño global del compromiso en puntaje')
            ->assertSee('C-PERF:');
    }

    public function test_score_year_shows_commitment_performance_when_not_searching(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedCommitmentGraph((int) $year->id, 'C-SHOW', 'I-SHOW', 3.5);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->assertViewHas('isSearching', false)
            ->assertSee('Desempeño global del compromiso en puntaje');
    }

    public function test_live_search_narrows_visible_commitments_for_score_year(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedCommitmentGraph((int) $year->id, 'C-ALPHA', 'I-ALPHA', 3.5);
        $this->seedCommitmentGraph((int) $year->id, 'C-BETA', 'I-BETA', 2.0);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->assertSee('C-ALPHA:')
            ->assertSee('C-BETA:')
            ->set('search', 'ALPHA')
            ->assertSee('C-ALPHA:')
            ->assertDontSee('C-BETA:');
    }

    public function test_live_search_narrows_visible_commitments(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedCommitmentGraph((int) $year->id, 'C-ALPHA', 'I-ALPHA', 80.0);
        $this->seedCommitmentGraph((int) $year->id, 'C-BETA', 'I-BETA', 50.0);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->assertSee('C-ALPHA:')
            ->assertSee('C-BETA:')
            ->set('search', 'ALPHA')
            ->assertSee('C-ALPHA:')
            ->assertDontSee('C-BETA:');
    }

    public function test_search_shows_only_matching_indicators_within_commitment_legacy_year(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedCommitmentWithTwoIndicators((int) $year->id, 'C-MIX', 'I-VALECH', 'I-OTRO-NOMBRE');

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->set('search', 'VALECH')
            ->assertSee('I-VALECH')
            ->assertDontSee('I-OTRO-NOMBRE');
    }

    public function test_search_shows_only_matching_indicators_within_commitment_score_year(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedCommitmentWithTwoIndicators((int) $year->id, 'C-MIX', 'I-primaria', 'I-secundaria');

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->set('search', 'primaria')
            ->assertSee('I-primaria')
            ->assertDontSee('I-secundaria');
    }

    public function test_search_finds_commitment_by_indicator_clave(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedCommitmentGraph((int) $year->id, 'C-HIDE', 'I-FIND', 4.0);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->set('search', 'I-FIND')
            ->assertSee('C-HIDE:')
            ->assertSee('I-FIND');
    }

    public function test_empty_search_lists_all_commitments_for_year(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->seedCommitmentGraph((int) $year->id, 'C-ONE', 'I-ONE', 100.0);
        $this->seedCommitmentGraph((int) $year->id, 'C-TWO', 'I-TWO', 50.0);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->set('search', '')
            ->assertSee('C-ONE:')
            ->assertSee('C-TWO:');
    }

    public function test_ambito_filter_shows_only_matching_indicators(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedCommitmentWithTwoIndicators((int) $year->id, 'C-AMB', 'I-GES', 'I-SIN');

        $ambito = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();
        $tagged = ComIndicator::query()->where('clave', 'I-GES')->firstOrFail();
        app(IndicatorAmbitoAssignmentService::class)->sync($tagged, [$ambito->id]);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->set('ambitoFilter', (string) $ambito->id)
            ->assertViewHas('isFiltering', true)
            ->assertSee('I-GES')
            ->assertDontSee('I-SIN')
            ->assertDontSee('Desempeño global del compromiso en puntaje');
    }

    public function test_ambito_labels_under_indicator_name_visible_only_for_parametrizador(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->seedCommitmentGraph((int) $year->id, 'C-TAG', 'I-TAG', 3.5);

        $ambito = IndicatorAmbito::query()->where('slug', 'ges')->firstOrFail();
        $indicator = ComIndicator::query()->where('clave', 'I-TAG')->firstOrFail();
        app(IndicatorAmbitoAssignmentService::class)->sync($indicator, [$ambito->id]);

        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $parametrizador = User::factory()->create([
            'role_id' => $paramRole->id,
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
        ]);
        $parametrizador->role()->associate($paramRole)->save();

        $badgeHtml = '<span class="label label-info" style="font-weight:normal; margin-right:2px;">'.$ambito->nombre.'</span>';

        $this->actingAs($parametrizador)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->assertSeeHtml('>'.$ambito->nombre.'<')
            ->assertSeeHtml('/admin/dashboard/ambitos/'.$ambito->slug);

        $this->actingAs($this->user)
            ->withSession(['indicator_year' => $year]);

        $html = Livewire::test(SearchCommitmentComponent::class)->html();

        $this->assertStringNotContainsString($badgeHtml, $html);
    }

    private function seedCommitmentWithTwoIndicators(
        int $yearId,
        string $commitmentClave,
        string $firstIndicatorClave,
        string $secondIndicatorClave,
    ): void {
        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'comges-'.$commitmentClave,
            'nombre_indicador' => 'COMGES '.$commitmentClave,
            'route' => 'compromisos-'.$commitmentClave,
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
            'clave' => $commitmentClave,
            'nombre' => 'Compromiso '.$commitmentClave,
            'year_id' => $yearId,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$firstIndicatorClave, $secondIndicatorClave] as $indicatorClave) {
            $indicatorId = (int) DB::table('com_indicators')->insertGetId([
                'com_commitment_id' => $commitmentId,
                'clave' => $indicatorClave,
                'nombre' => 'Indicador '.$indicatorClave,
                'ponderacion_indicador' => 50,
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

            DB::table('com_evaluations')->insert([
                'com_action_id' => $actionId,
                'cen_hie_dependency_id' => $this->cenHieDependencyId,
                'tipo_evaluacion' => 'AUTOEVALUACION',
                'numerador' => 80,
                'denominador' => 100,
                'resultado' => 80,
                'cumplimiento' => 80,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedCommitmentGraph(int $yearId, string $commitmentClave, string $indicatorClave, float $cumplimiento): void
    {
        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'comges-'.$commitmentClave,
            'nombre_indicador' => 'COMGES '.$commitmentClave,
            'route' => 'compromisos-'.$commitmentClave,
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
            'clave' => $commitmentClave,
            'nombre' => 'Compromiso '.$commitmentClave,
            'year_id' => $yearId,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => $indicatorClave,
            'nombre' => 'Indicador '.$indicatorClave,
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

        DB::table('com_evaluations')->insert([
            'com_action_id' => $actionId,
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
            'tipo_evaluacion' => 'AUTOEVALUACION',
            'numerador' => $cumplimiento,
            'denominador' => 100,
            'resultado' => $cumplimiento,
            'cumplimiento' => (int) round($cumplimiento),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function registerStubRoutes(): void
    {
        Route::get('/admin/indicadores-comges/{id}', fn () => '')->name('voyager.indicadores-comges.show');
        Route::get('/admin/indicadores-comges/create', fn () => '')->name('voyager.indicadores-comges.create');
        Route::get('/admin/compromisos/{id}/edit', fn () => '')->name('voyager.compromisos.edit');
    }
}
