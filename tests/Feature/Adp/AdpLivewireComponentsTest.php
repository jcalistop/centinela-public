<?php

namespace Tests\Feature\Adp;

use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Livewire\Adp\AssignmentComponent;
use App\Livewire\Adp\Catalog\PositionComponent;
use App\Livewire\Adp\SensitivityComponent;
use App\Models\AdpAgreement;
use App\Models\AdpAgreementPeriod;
use App\Models\AdpIndicator;
use App\Models\AdpObjective;
use App\Models\AdpPosition;
use App\Models\AdpPositionAssignment;
use App\Models\AdpSensitivityTable;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdpLivewireComponentsTest extends TestCase
{
    use RefreshDatabase;

    private IndicatorType $indicatorType;

    private User $adminUser;

    private User $parametrizador;

    private User $controlGestion;

    protected function setUp(): void
    {
        parent::setUp();

        Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->indicatorType = IndicatorType::query()->where('clave_indicador', 'adp')->firstOrFail();

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud 1',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $parametrizadorRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);

        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);
        $this->parametrizador = User::factory()->create(['role_id' => $parametrizadorRole->id, 'cen_hie_dependency_id' => 1]);
        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
    }

    private function makeCenter(string $codigo, string $nombre_oficial): CenCenter
    {
        return CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => $codigo,
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type_id' => 1,
            'cen_scope_id' => 1,
            'nombre_oficial' => $nombre_oficial,
            'nombre_corto' => $nombre_oficial,
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

    /** @test */
    public function position_component_renders_for_admin(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(PositionComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->assertStatus(200)
            ->assertSee('Catálogo global de cargos ADP');
    }

    /** @test */
    public function position_component_renders_for_parametrizador(): void
    {
        $this->actingAs($this->parametrizador);

        Livewire::test(PositionComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->assertStatus(200)
            ->assertSee('Catálogo global de cargos ADP');
    }

    /** @test */
    public function paramatrizador_can_create_position(): void
    {
        $this->actingAs($this->parametrizador);

        Livewire::test(PositionComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->call('create')
            ->assertSet('showForm', true)
            ->set('codigo', 'DIR')
            ->set('nombre', 'Director')
            ->set('descripcion', 'Director de establecimiento')
            ->call('save')
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('adp_positions', [
            'codigo' => 'DIR',
            'nombre' => 'Director',
        ]);
    }

    /** @test */
    public function paramatrizador_can_edit_position(): void
    {
        $this->actingAs($this->parametrizador);

        $position = AdpPosition::unguarded(fn () => AdpPosition::create([
            'codigo' => 'SUB',
            'nombre' => 'Subdirector',
        ]));

        Livewire::test(PositionComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->call('edit', $position->id)
            ->assertSet('showForm', true)
            ->set('nombre', 'Subdirector Médico')
            ->call('save')
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('adp_positions', [
            'id' => $position->id,
            'nombre' => 'Subdirector Médico',
        ]);
    }

    /** @test */
    public function paramatrizador_can_delete_position(): void
    {
        $this->actingAs($this->parametrizador);

        $position = AdpPosition::unguarded(fn () => AdpPosition::create([
            'codigo' => 'TEMP',
            'nombre' => 'Temporal',
        ]));

        Livewire::test(PositionComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->call('delete', $position->id);

        $this->assertDatabaseMissing('adp_positions', ['id' => $position->id]);
    }

    /** @test */
    public function assignment_component_renders_for_admin(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(AssignmentComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->assertStatus(200)
            ->assertSee('Asignaciones persona-cargo por Servicio de Salud');
    }

    /** @test */
    public function assignment_component_renders_for_control_gestion(): void
    {
        $this->actingAs($this->controlGestion);

        Livewire::test(AssignmentComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->assertStatus(200)
            ->assertSee('Asignaciones persona-cargo por Servicio de Salud');
    }

    /** @test */
    public function admin_can_create_assignment(): void
    {
        $this->actingAs($this->adminUser);

        $position = AdpPosition::unguarded(fn () => AdpPosition::create(['codigo' => 'DIR', 'nombre' => 'Director']));
        $center = $this->makeCenter('HOS-ADP-1', 'Hospital Test');

        Livewire::test(AssignmentComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->call('create')
            ->assertSet('showForm', true)
            ->set('user_id', $this->controlGestion->id)
            ->set('adp_position_id', $position->id)
            ->set('cen_center_id', $center->id)
            ->set('start_date', '2026-01-01')
            ->call('save')
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('adp_position_assignments', [
            'user_id' => $this->controlGestion->id,
            'adp_position_id' => $position->id,
            'cen_center_id' => $center->id,
        ]);
    }

    /** @test */
    public function admin_can_delete_assignment(): void
    {
        $this->actingAs($this->adminUser);

        $position = AdpPosition::unguarded(fn () => AdpPosition::create(['codigo' => 'TEST', 'nombre' => 'Test']));
        $center = $this->makeCenter('CEN-DEL-1', 'Center Test');

        $assignment = AdpPositionAssignment::query()->create([
            'user_id' => $this->controlGestion->id,
            'adp_position_id' => $position->id,
            'cen_center_id' => $center->id,
            'start_date' => '2026-01-01',
        ]);

        Livewire::test(AssignmentComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->call('delete', $assignment->id);

        $this->assertDatabaseMissing('adp_position_assignments', ['id' => $assignment->id]);
    }

    /** @test */
    public function sensitivity_component_renders_for_admin(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(SensitivityComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->assertStatus(200)
            ->assertSee('Tablas de sensibilidad por indicador');
    }

    /** @test */
    public function admin_can_create_sensitivity_table(): void
    {
        $this->actingAs($this->adminUser);

        $position = AdpPosition::unguarded(fn () => AdpPosition::create(['codigo' => 'DIR', 'nombre' => 'Director']));
        $center = $this->makeCenter('HOS-SENS-1', 'Hospital Test');
        $assignment = AdpPositionAssignment::query()->create([
            'user_id' => $this->controlGestion->id,
            'adp_position_id' => $position->id,
            'cen_center_id' => $center->id,
            'start_date' => '2026-01-01',
        ]);
        $agreement = AdpAgreement::query()->create([
            'adp_position_assignment_id' => $assignment->id,
            'duration_years' => 1,
            'agreement_start' => '2026-01-01',
            'status' => 'active',
        ]);
        $period = AdpAgreementPeriod::query()->create([
            'adp_agreement_id' => $agreement->id,
            'sequence' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $objective = AdpObjective::query()->create([
            'adp_agreement_period_id' => $period->id,
            'clave' => 'OBJ-1',
            'nombre' => 'Objetivo 1',
            'orden' => 1,
        ]);
        $indicator = AdpIndicator::unguarded(fn () => AdpIndicator::create([
            'adp_objective_id' => $objective->id,
            'clave' => 'IND-1',
            'nombre' => 'Indicador 1',
            'calculation_method' => 'sensitivity',
            'periodicidad' => 'ANUAL',
            'estado' => true,
        ]));

        Livewire::test(SensitivityComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->set('selectedAgreementId', $agreement->id)
            ->set('selectedPeriodId', $period->id)
            ->set('selectedIndicatorId', $indicator->id)
            ->call('createTable')
            ->assertSet('editingTableId', null)
            ->set('tableName', 'Sensitivity Test')
            ->set('rows', [
                [
                    'orden' => 1,
                    'valor_minimo' => '0',
                    'operador_minimo' => '>=',
                    'valor_maximo' => '100',
                    'operador_maximo' => '<=',
                    'cumplimiento' => '100',
                ],
            ])
            ->assertHasNoErrors()
            ->call('saveTable')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('adp_sensitivity_tables', [
            'adp_indicator_id' => $indicator->id,
            'nombre' => 'Sensitivity Test',
        ]);

        /** @var AdpSensitivityTable $table */
        $table = AdpSensitivityTable::query()->where('adp_indicator_id', $indicator->id)->first();
        $this->assertNotNull($table);
        $this->assertCount(1, $table->rows);
    }

    /** @test */
    public function admin_can_delete_sensitivity_table(): void
    {
        $this->actingAs($this->adminUser);

        $position = AdpPosition::unguarded(fn () => AdpPosition::create(['codigo' => 'DIR', 'nombre' => 'Director']));
        $center = $this->makeCenter('CEN-SDEL-1', 'Center Del');
        $assignment = AdpPositionAssignment::query()->create([
            'user_id' => $this->controlGestion->id,
            'adp_position_id' => $position->id,
            'cen_center_id' => $center->id,
            'start_date' => '2026-01-01',
        ]);
        $agreement = AdpAgreement::query()->create([
            'adp_position_assignment_id' => $assignment->id,
            'duration_years' => 1,
            'agreement_start' => '2026-01-01',
            'status' => 'active',
        ]);
        $period = AdpAgreementPeriod::query()->create([
            'adp_agreement_id' => $agreement->id,
            'sequence' => 1,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $objective = AdpObjective::query()->create([
            'adp_agreement_period_id' => $period->id,
            'clave' => 'OBJ-DEL',
            'nombre' => 'Objetivo to delete',
            'orden' => 1,
        ]);
        $indicator = AdpIndicator::unguarded(fn () => AdpIndicator::create([
            'adp_objective_id' => $objective->id,
            'clave' => 'SENS-1',
            'nombre' => 'Sensitivity Indicator',
            'calculation_method' => 'sensitivity',
            'periodicidad' => 'ANUAL',
            'estado' => true,
        ]));

        $table = AdpSensitivityTable::unguarded(fn () => AdpSensitivityTable::create([
            'adp_indicator_id' => $indicator->id,
            'nombre' => 'Table to delete',
        ]));

        Livewire::test(SensitivityComponent::class, ['indicatorTypeId' => $this->indicatorType->id])
            ->set('selectedIndicatorId', $indicator->id)
            ->call('deleteTable', $table->id);

        $this->assertDatabaseMissing('adp_sensitivity_tables', ['id' => $table->id]);
    }
}
