<?php

namespace Tests\Feature\Comges;

use App\Livewire\SearchCommitmentComponent;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\View\Components\Indicadores\IndicatorResultsComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class ComgesLegacyDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $controlGestion;

    private int $cenHieDependencyId;

    private int $year2024Id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cenHieDependencyId = $this->seedDependency();
        $this->year2024Id = (int) DB::table('years')->insertGetId([
            'ano' => 2024,
            'default' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();
    }

    public function test_indicator_results_component_uses_legacy_view_for_year_2024(): void
    {
        $year = Year::find($this->year2024Id);

        $this->withSession(['indicator_year' => $year]);

        $indicator = (object) ['comIndicatorCutoffs' => collect()];

        $view = (new IndicatorResultsComponent($indicator))->render();

        $this->assertSame('components.comges.indicadores.legacy.indicator-results-component', $view->name());
    }

    public function test_livewire_search_commitment_shows_percentage_semaforo_for_2024(): void
    {
        $this->registerStubVoyagerRoutesForLivewire();

        $year = Year::find($this->year2024Id);
        $this->seedCommitmentWithEvaluation($this->year2024Id, 85.0);

        $this->actingAs($this->controlGestion)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(SearchCommitmentComponent::class)
            ->assertViewHas('useScoreScale', false)
            ->assertSee('semaforo orange')
            ->assertSee('85%');
    }

    private function registerStubVoyagerRoutesForLivewire(): void
    {
        Route::get('/admin/indicadores-comges/{id}', fn () => '')->name('voyager.indicadores-comges.show');
        Route::get('/admin/indicadores-comges/create', fn () => '')->name('voyager.indicadores-comges.create');
        Route::get('/admin/compromisos/{id}/edit', fn () => '')->name('voyager.compromisos.edit');
    }

    /**
     * @return array{commitment_id: int, indicator_id: int}
     */
    private function seedCommitmentWithEvaluation(int $yearId, float $cumplimiento): array
    {
        $suffix = bin2hex(random_bytes(4));

        $indicatorTypeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'comges-'.$suffix,
            'nombre_indicador' => 'COMGES '.$suffix,
            'descripcion_indicador' => null,
            'icon_class' => null,
            'route' => 'compromisos-'.$suffix,
            'view' => null,
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1]),
            'nombre_corte' => 'Corte '.$suffix,
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitmentId = (int) DB::table('com_commitments')->insertGetId([
            'indicator_type_id' => $indicatorTypeId,
            'clave' => 'C-'.$suffix,
            'nombre' => 'Compromiso test',
            'descripcion' => null,
            'orden' => 1,
            'year_id' => $yearId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorId = (int) DB::table('com_indicators')->insertGetId([
            'com_commitment_id' => $commitmentId,
            'clave' => 'I-'.$suffix,
            'nombre' => 'Indicador test',
            'ponderacion_indicador' => 100,
            'descripcion' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cutoffLinkId = (int) DB::table('com_indicator_cutoffs')->insertGetId([
            'com_indicator_id' => $indicatorId,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
            'promedio_pais' => null,
            'evaluacion_local' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $actionId = (int) DB::table('com_actions')->insertGetId([
            'com_indicator_cutoff_id' => $cutoffLinkId,
            'numero' => '1',
            'meta_texto' => null,
            'tipo_medio' => null,
            'medio_verificacion' => null,
            'formula' => null,
            'fuente' => null,
            'observaciones' => null,
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

        return [
            'commitment_id' => $commitmentId,
            'indicator_id' => $indicatorId,
        ];
    }

    private function seedDependency(): int
    {
        return (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-LEGACY',
            'nombre' => 'Servicio Legacy',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
