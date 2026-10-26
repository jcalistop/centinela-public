<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Livewire\MetasInstrument\CenterSelectionComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetCenterAdosado;
use App\Models\MetCenterInclusion;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CenterAdosadosTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $indicatorType;

    private IndicatorTypeSetting $setting;

    private User $controlGestion;

    private CenCenter $centerMadre;

    private CenCenter $centerOtro;

    private CenCenter $candidatoAdosado;

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

        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => true]);

        $this->setting = IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        $this->createCenterDependencies();

        $this->centerMadre = $this->createCenter('CEN-A', 'Hospital A', null);
        $this->centerOtro = $this->createCenter('CEN-B', 'Hospital B', null);
        $this->candidatoAdosado = $this->createCenter('CEN-A1', 'CECOSF A1', 'CEN-A');

        // El centro madre está incluido en la selección
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerMadre->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));
    }

    /** @test */
    public function can_open_adosado_panel_for_included_center(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('openAdosadoPanel', $this->centerMadre->id, $this->centerMadre->nombre_corto)
            ->assertSet('showAdosadoPanel', true)
            ->assertSet('adosadoPanelCenterId', $this->centerMadre->id)
            ->assertSet('adosadoPanelCenterName', $this->centerMadre->nombre_corto);
    }

    /** @test */
    public function can_add_valid_adosado(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('openAdosadoPanel', $this->centerMadre->id, $this->centerMadre->nombre_corto)
            ->set('selectedAdosadoToAdd', $this->candidatoAdosado->id)
            ->call('addAdosado')
            ->assertHasNoErrors('adosado');

        $this->assertDatabaseHas('met_center_adosados', [
            'cen_center_madre_id' => $this->centerMadre->id,
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
        ]);
    }

    /** @test */
    public function adosados_are_scoped_per_instrument(): void
    {
        $otherInstrument = IndicatorType::query()->where('route', 'metas-19664')->firstOrFail();

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $otherInstrument->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerMadre->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $otherInstrument->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('openAdosadoPanel', $this->centerMadre->id, $this->centerMadre->nombre_corto)
            ->set('selectedAdosadoToAdd', $this->candidatoAdosado->id)
            ->call('addAdosado')
            ->assertHasNoErrors('adosado');

        $this->assertDatabaseHas('met_center_adosados', [
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
            'indicator_type_id' => $this->indicatorType->id,
        ]);

        $this->assertDatabaseMissing('met_center_adosados', [
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
            'indicator_type_id' => $otherInstrument->id,
        ]);

        Livewire::test(CenterSelectionComponent::class, [
            'indicatorTypeId' => $otherInstrument->id,
        ])
            ->assertDontSee($this->candidatoAdosado->nombre_corto);
    }

    /** @test */
    public function cannot_add_adosado_that_is_a_madre_incluida(): void
    {
        // El centerOtro también está incluido como madre
        MetCenterInclusion::unguarded(fn () => MetCenterInclusion::create([
            'cen_center_id' => $this->centerOtro->id,
            'year_id' => $this->year->id,
            'indicator_type_id' => $this->indicatorType->id,
            'cen_hie_dependency_id' => 1,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('openAdosadoPanel', $this->centerMadre->id, $this->centerMadre->nombre_corto)
            ->set('selectedAdosadoToAdd', $this->centerOtro->id)
            ->call('addAdosado')
            ->assertHasErrors('adosado');

        $this->assertDatabaseMissing('met_center_adosados', [
            'cen_center_adosado_id' => $this->centerOtro->id,
        ]);
    }

    /** @test */
    public function cannot_add_adosado_already_adosado_to_another_madre(): void
    {
        // El candidatoAdosado ya está adosado al centerOtro
        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->centerOtro->id,
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('openAdosadoPanel', $this->centerMadre->id, $this->centerMadre->nombre_corto)
            ->set('selectedAdosadoToAdd', $this->candidatoAdosado->id)
            ->call('addAdosado')
            ->assertHasErrors('adosado');

        $this->assertDatabaseMissing('met_center_adosados', [
            'cen_center_madre_id' => $this->centerMadre->id,
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
        ]);
    }

    /** @test */
    public function can_remove_adosado(): void
    {
        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->centerMadre->id,
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('openAdosadoPanel', $this->centerMadre->id, $this->centerMadre->nombre_corto)
            ->call('removeAdosado', $this->candidatoAdosado->id)
            ->assertHasNoErrors('adosado');

        $this->assertDatabaseMissing('met_center_adosados', [
            'cen_center_madre_id' => $this->centerMadre->id,
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
            'year_id' => $this->year->id,
        ]);
    }

    /** @test */
    public function load_default_adosados_is_idempotent(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        $component = Livewire::test(CenterSelectionComponent::class)
            ->call('openAdosadoPanel', $this->centerMadre->id, $this->centerMadre->nombre_corto);

        // Primera llamada
        $component->call('loadDefaultAdosados');
        $this->assertDatabaseHas('met_center_adosados', [
            'cen_center_madre_id' => $this->centerMadre->id,
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
        ]);
        $count1 = MetCenterAdosado::query()
            ->forInstrument($this->indicatorType->id)
            ->where('cen_center_madre_id', $this->centerMadre->id)
            ->count();

        // Segunda llamada: no duplica
        $component->call('loadDefaultAdosados');
        $count2 = MetCenterAdosado::query()
            ->forInstrument($this->indicatorType->id)
            ->where('cen_center_madre_id', $this->centerMadre->id)
            ->count();

        $this->assertSame($count1, $count2);
    }

    /** @test */
    public function load_default_adosados_skips_already_adosado_to_another_madre(): void
    {
        // El candidato ya está adosado al centerOtro
        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->centerOtro->id,
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('openAdosadoPanel', $this->centerMadre->id, $this->centerMadre->nombre_corto)
            ->call('loadDefaultAdosados');

        // No debe crearse para centerMadre
        $this->assertDatabaseMissing('met_center_adosados', [
            'cen_center_madre_id' => $this->centerMadre->id,
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
        ]);
    }

    /** @test */
    public function adosado_management_is_blocked_when_instrument_is_cerrado(): void
    {
        IndicatorTypeSetting::unguarded(fn () => $this->setting->update(['etapa_vigente' => 'CERRADO']));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('openAdosadoPanel', $this->centerMadre->id, $this->centerMadre->nombre_corto)
            ->set('selectedAdosadoToAdd', $this->candidatoAdosado->id)
            ->call('addAdosado')
            ->assertHasErrors('adosado');

        $this->assertDatabaseMissing('met_center_adosados', [
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
        ]);
    }

    /** @test */
    public function close_adosado_panel_resets_state(): void
    {
        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('openAdosadoPanel', $this->centerMadre->id, $this->centerMadre->nombre_corto)
            ->assertSet('showAdosadoPanel', true)
            ->call('closeAdosadoPanel')
            ->assertSet('showAdosadoPanel', false)
            ->assertSet('adosadoPanelCenterId', null)
            ->assertSet('adosadoPanelCenterName', '');
    }

    /** @test */
    public function adosado_center_is_not_shown_in_selection_list(): void
    {
        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->centerMadre->id,
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        // El código 'CEN-A1' no debe aparecer en la tabla principal de participantes.
        Livewire::test(CenterSelectionComponent::class)
            ->assertDontSee('CEN-A1');
    }

    /** @test */
    public function cannot_include_a_center_that_is_already_an_adosado(): void
    {
        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->centerMadre->id,
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
        ]));

        $this->actingAs($this->controlGestion)->withSession(['indicator_year' => $this->year]);

        Livewire::test(CenterSelectionComponent::class)
            ->call('toggleCenter', $this->candidatoAdosado->id)
            ->assertHasErrors('general');

        $this->assertDatabaseMissing('met_center_inclusions', [
            'cen_center_id' => $this->candidatoAdosado->id,
            'year_id' => $this->year->id,
        ]);
    }

    /** @test */
    public function job_reads_adosados_from_met_center_adosados_table(): void
    {
        MetCenterAdosado::unguarded(fn () => MetCenterAdosado::create([
            'cen_center_madre_id' => $this->centerMadre->id,
            'cen_center_adosado_id' => $this->candidatoAdosado->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'indicator_type_id' => $this->indicatorType->id,
        ]));

        // Verificar que la query que usa el job resuelve los adosados desde met_center_adosados
        $adosadoIds = MetCenterAdosado::query()
            ->forInstrument($this->indicatorType->id)
            ->where('cen_center_madre_id', $this->centerMadre->id)
            ->where('year_id', $this->year->id)
            ->where('cen_hie_dependency_id', 1)
            ->pluck('cen_center_adosado_id')
            ->toArray();

        $this->assertCount(1, $adosadoIds);
        $this->assertContains($this->candidatoAdosado->id, $adosadoIds);
    }

    /** @test */
    public function job_works_with_empty_adosados_for_a_center(): void
    {
        // No hay registros en met_center_adosados

        $adosadoIds = MetCenterAdosado::query()
            ->forInstrument($this->indicatorType->id)
            ->where('cen_center_madre_id', $this->centerMadre->id)
            ->where('year_id', $this->year->id)
            ->where('cen_hie_dependency_id', 1)
            ->pluck('cen_center_adosado_id')
            ->toArray();

        $this->assertEmpty($adosadoIds);
    }

    private function createCenter(string $codigo, string $nombre, ?string $codigoMadre, int $depId = 1): CenCenter
    {
        return CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => $codigo,
            'cod_antiguo' => null,
            'codigo_madre' => $codigoMadre,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => $depId,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => $nombre,
            'nombre_corto' => $nombre,
            'cen_certification_id' => 1,
            'cen_adm_dependency_id' => 1,
            'cen_adm_dependency' => 'Servicio de Salud',
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
