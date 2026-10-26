<?php

namespace Tests\Feature\Comges;

use App\Filament\ServicioSalud\Pages\BrowseComgesCommitments;
use App\Helpers\ModuleAccess;
use App\Livewire\Comges\FilamentBrowseCommitments;
use App\Livewire\Filament\PanelContextBar;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentBrowseComgesCommitmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $controlGestion;

    private int $cenHieDependencyId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['centinela.filament.enabled' => true]);
        Filament::setCurrentPanel('servicio-salud');

        ModuleAccess::flushRequestMemo();

        Route::get('/admin/indicadores-comges/{id}', fn () => '')->name('voyager.indicadores-comges.show');

        $this->cenHieDependencyId = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-C1A',
            'nombre' => 'SS C1a Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
        ]);
        $this->admin->role()->associate($adminRole)->save();

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $this->cenHieDependencyId,
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();

        IndicatorType::unguarded(fn () => IndicatorType::updateOrCreate(
            ['id' => 1],
            [
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'route' => 'voyager.compromisos.index',
                'estado' => true,
                'orden' => 1,
            ]
        ));
    }

    public function test_guest_is_redirected_from_filament_comges_browse(): void
    {
        $this->get('/servicio-salud/comges/compromisos')->assertRedirect();
    }

    public function test_admin_gets_forbidden_when_flag_is_off(): void
    {
        config(['centinela.filament.comges_browse_enabled' => false]);

        $this->actingAs($this->admin)
            ->get('/servicio-salud/comges/compromisos')
            ->assertForbidden();
    }

    public function test_admin_can_view_browse_when_flag_is_on(): void
    {
        config(['centinela.filament.comges_browse_enabled' => true]);
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->enableComgesForSs($year->id);

        $this->actingAs($this->admin)
            ->withSession(['indicator_year' => $year])
            ->get('/servicio-salud/comges/compromisos')
            ->assertOk()
            ->assertSeeLivewire(FilamentBrowseCommitments::class)
            ->assertSeeLivewire(PanelContextBar::class)
            ->assertSee('SS C1a Test');
    }

    public function test_control_gestion_can_access_panel_when_browse_flag_on(): void
    {
        config(['centinela.filament.comges_browse_enabled' => true]);
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->enableComgesForSs($year->id);

        $this->actingAs($this->controlGestion);

        $this->assertTrue($this->controlGestion->canAccessPanel(
            filament()->getPanel('servicio-salud')
        ));

        $this->withSession(['indicator_year' => $year])
            ->get('/servicio-salud/comges/compromisos')
            ->assertOk();
    }

    public function test_livewire_score_year_shows_kpi_and_desempeno_labels(): void
    {
        config(['centinela.filament.comges_browse_enabled' => true]);
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->enableComgesForSs($year->id);
        $this->seedCommitmentGraph((int) $year->id, 'C-C1A', 'I-C1A', 3.5);

        $this->actingAs($this->controlGestion)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(FilamentBrowseCommitments::class)
            ->assertOk()
            ->assertSee('Compromisos')
            ->assertSee('Corte vigente')
            ->assertSee('Verificador pendiente')
            ->assertSee('Leyenda')
            ->assertSee('Desempeño compromiso')
            ->assertSee('C-C1A')
            ->assertSee('Auto')
            ->assertViewHas('useScoreScale', true);
    }

    public function test_livewire_legacy_year_shows_percentage_hint(): void
    {
        config(['centinela.filament.comges_browse_enabled' => true]);
        $year = Year::unguarded(fn () => Year::create(['ano' => 2024, 'default' => 1]));
        $this->enableComgesForSs($year->id);
        $this->seedCommitmentGraph((int) $year->id, 'C-LEG', 'I-LEG', 80.0);

        $this->actingAs($this->controlGestion)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(FilamentBrowseCommitments::class)
            ->assertOk()
            ->assertViewHas('useScoreScale', false)
            ->assertSee('Escala legacy')
            ->assertSee('C-LEG');
    }

    public function test_tenancy_other_ss_evaluations_do_not_inflate_visible_score_cells(): void
    {
        config(['centinela.filament.comges_browse_enabled' => true]);
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->enableComgesForSs($year->id);

        $otherSs = (int) DB::table('cen_hie_dependencies')->insertGetId([
            'codigo' => 'SS-OTHER',
            'nombre' => 'Otra SS',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->seedCommitmentGraph((int) $year->id, 'C-TEN', 'I-TEN', 3.5, $otherSs);

        $this->actingAs($this->controlGestion)
            ->withSession(['indicator_year' => $year]);

        Livewire::test(FilamentBrowseCommitments::class)
            ->assertOk()
            ->assertSee('C-TEN')
            ->assertSee('—');
    }

    public function test_page_can_access_requires_flag_and_module(): void
    {
        config(['centinela.filament.comges_browse_enabled' => false]);
        $this->actingAs($this->admin);
        $this->assertFalse(BrowseComgesCommitments::canAccess());

        config(['centinela.filament.comges_browse_enabled' => true]);
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->enableComgesForSs($year->id);
        ModuleAccess::flushRequestMemo();
        $this->assertTrue(BrowseComgesCommitments::canAccess());
    }

    private function enableComgesForSs(int $yearId): void
    {
        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::updateOrCreate(
            [
                'indicator_type_id' => 1,
                'year_id' => $yearId,
                'cen_hie_dependency_id' => $this->cenHieDependencyId,
            ],
            ['habilitado' => true]
        ));
        ModuleAccess::flushRequestMemo();
    }

    private function seedCommitmentGraph(
        int $yearId,
        string $commitmentClave,
        string $indicatorClave,
        float $cumplimiento,
        ?int $evaluationSsId = null,
    ): void {
        $evaluationSsId ??= $this->cenHieDependencyId;

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
            'cen_hie_dependency_id' => $evaluationSsId,
            'tipo_evaluacion' => 'AUTOEVALUACION',
            'numerador' => $cumplimiento,
            'denominador' => 100,
            'resultado' => $cumplimiento,
            'cumplimiento' => (int) round($cumplimiento),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
