<?php

namespace Tests\Feature\MetasInstrument;

use App\Enums\IndicatorType\EtapaVigente;
use App\Livewire\MetasInstrument\MeasurementUnitsManagerComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\MetDataField;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\MetMeasurementUnit;
use App\Models\MetMonthlyData;
use App\Models\MetResult;
use App\Models\MetUnitType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class MeasurementUnitsManagerTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    private Year $year;

    private CenCenter $center;

    private User $admin;

    private IndicatorType $indicatorType;

    private MetUnitType $enabledType;

    private MetUnitType $disabledType;

    protected function setUp(): void
    {
        parent::setUp();

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->admin->role()->associate($adminRole)->save();

        $this->indicatorType = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'metas-20707-test',
            'nombre_indicador' => 'Metas 20.707 Test',
            'route' => 'metas-20707-test',
            'view' => null,
            'estado' => true,
            'orden' => 99,
            'measurement_granularity' => 'internal_unit',
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => EtapaVigente::Autoevaluacion,
            'habilitado' => true,
        ]));

        $this->center = $this->createMinimalCenCenter([
            'codigo' => 'CEN-20707',
            'cen_hie_dependency_id' => 1,
        ]);

        $this->enabledType = MetUnitType::create([
            'codigo' => 'T-EN',
            'nombre' => 'Habilitado',
            'orden' => 1,
            'enabled' => true,
        ]);

        $this->disabledType = MetUnitType::create([
            'codigo' => 'T-DIS',
            'nombre' => 'Deshabilitado',
            'orden' => 2,
            'enabled' => false,
        ]);

        $this->actingAs($this->admin)->withSession(['indicator_year' => $this->year]);
    }

    /** @test */
    public function admin_can_edit_unit_fields(): void
    {
        $unit = $this->createUnit('U-OLD', 'Nombre viejo', $this->enabledType->id);

        Livewire::test(MeasurementUnitsManagerComponent::class, [
            'centerId' => $this->center->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('startEdit', $unit->id)
            ->set('editCodigo', 'U-NEW')
            ->set('editNombre', 'Nombre nuevo')
            ->set('editMetUnitTypeId', $this->enabledType->id)
            ->call('updateUnit')
            ->assertHasNoErrors()
            ->assertDispatched('units-updated');

        $this->assertDatabaseHas('met_measurement_units', [
            'id' => $unit->id,
            'codigo' => 'U-NEW',
            'nombre' => 'Nombre nuevo',
        ]);
    }

    /** @test */
    public function edit_rejects_duplicate_codigo_in_same_center_year(): void
    {
        $this->createUnit('U-A', 'Unidad A', $this->enabledType->id);
        $unitB = $this->createUnit('U-B', 'Unidad B', $this->enabledType->id);

        Livewire::test(MeasurementUnitsManagerComponent::class, [
            'centerId' => $this->center->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('startEdit', $unitB->id)
            ->set('editCodigo', 'U-A')
            ->set('editMetUnitTypeId', $this->enabledType->id)
            ->call('updateUnit')
            ->assertHasErrors(['editCodigo']);
    }

    /** @test */
    public function edit_includes_disabled_type_when_it_is_current(): void
    {
        $unit = $this->createUnit('U-DIS', 'Con tipo off', $this->disabledType->id);

        $component = Livewire::test(MeasurementUnitsManagerComponent::class, [
            'centerId' => $this->center->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])->call('startEdit', $unit->id);

        $typeIds = collect($component->viewData('editUnitTypes'))->pluck('id')->all();

        $this->assertContains($this->disabledType->id, $typeIds);
    }

    /** @test */
    public function admin_can_delete_unused_unit(): void
    {
        $unit = $this->createUnit('U-DEL', 'Eliminar', $this->enabledType->id);

        Livewire::test(MeasurementUnitsManagerComponent::class, [
            'centerId' => $this->center->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('confirmDeleteUnit', $unit->id)
            ->call('deleteUnit')
            ->assertDispatched('units-updated');

        $this->assertDatabaseMissing('met_measurement_units', ['id' => $unit->id]);
    }

    /** @test */
    public function delete_blocked_when_unit_has_monthly_data(): void
    {
        $unit = $this->createUnit('U-MD', 'Con carga', $this->enabledType->id);
        $indicator = $this->createIndicator();
        $field = $this->createDataField($indicator);

        MetMonthlyData::unguarded(fn () => MetMonthlyData::create([
            'met_data_field_id' => $field->id,
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'met_measurement_unit_id' => $unit->id,
            'mes' => 1,
            'valor' => 10,
            'cen_hie_dependency_id' => 1,
        ]));

        Livewire::test(MeasurementUnitsManagerComponent::class, [
            'centerId' => $this->center->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('confirmDeleteUnit', $unit->id)
            ->call('deleteUnit');

        $this->assertDatabaseHas('met_measurement_units', ['id' => $unit->id]);
    }

    /** @test */
    public function delete_blocked_when_unit_has_indicator_configuration(): void
    {
        $unit = $this->createUnit('U-CFG', 'Con convenio', $this->enabledType->id);
        $indicator = $this->createIndicator();

        MetIndicatorConfiguration::unguarded(fn () => MetIndicatorConfiguration::create([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $this->center->id,
            'met_measurement_unit_id' => $unit->id,
            'seleccionado' => true,
            'aplica' => true,
        ]));

        Livewire::test(MeasurementUnitsManagerComponent::class, [
            'centerId' => $this->center->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('confirmDeleteUnit', $unit->id)
            ->call('deleteUnit');

        $this->assertDatabaseHas('met_measurement_units', ['id' => $unit->id]);
    }

    /** @test */
    public function delete_blocked_when_unit_has_results(): void
    {
        $unit = $this->createUnit('U-RES', 'Con resultados', $this->enabledType->id);
        $indicator = $this->createIndicator();

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $this->center->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'met_measurement_unit_id' => $unit->id,
            'periodo' => 'ENE',
            'numerador' => 10,
            'denominador' => 20,
            'resultado' => 50,
            'cumple_meta' => true,
        ]));

        Livewire::test(MeasurementUnitsManagerComponent::class, [
            'centerId' => $this->center->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->call('confirmDeleteUnit', $unit->id)
            ->call('deleteUnit');

        $this->assertDatabaseHas('met_measurement_units', ['id' => $unit->id]);
    }

    /** @test */
    public function mutations_blocked_when_instrument_is_closed(): void
    {
        IndicatorTypeSetting::query()
            ->where('indicator_type_id', $this->indicatorType->id)
            ->update(['etapa_vigente' => EtapaVigente::Cerrado]);

        $unit = $this->createUnit('U-CLS', 'Cerrado', $this->enabledType->id);

        Livewire::test(MeasurementUnitsManagerComponent::class, [
            'centerId' => $this->center->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])
            ->assertSee('CERRADO')
            ->call('startEdit', $unit->id)
            ->assertSet('editingUnitId', null)
            ->set('newCodigo', 'U-NUEVA')
            ->set('newMetUnitTypeId', $this->enabledType->id)
            ->call('saveNewUnit');

        $this->assertDatabaseMissing('met_measurement_units', ['codigo' => 'U-NUEVA']);
    }

    /** @test */
    public function user_without_cg_role_cannot_mount_component(): void
    {
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $referente->role()->associate($referenteRole)->save();

        $this->actingAs($referente)->withSession(['indicator_year' => $this->year]);

        Livewire::test(MeasurementUnitsManagerComponent::class, [
            'centerId' => $this->center->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])->assertStatus(403);
    }

    /** @test */
    public function center_from_other_ss_returns_forbidden(): void
    {
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS02',
            'nombre' => 'Otro SS',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $otherCenter = $this->createMinimalCenCenter([
            'codigo' => 'CEN-OTRO',
            'cen_hie_dependency_id' => 2,
        ]);

        Livewire::test(MeasurementUnitsManagerComponent::class, [
            'centerId' => $otherCenter->id,
            'indicatorTypeId' => $this->indicatorType->id,
        ])->assertStatus(403);
    }

    private function createUnit(string $codigo, string $nombre, int $typeId): MetMeasurementUnit
    {
        return MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $typeId,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));
    }

    private function createIndicator(): MetIndicator
    {
        return MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $this->indicatorType->id,
            'clave' => 'IND-'.uniqid(),
            'nombre' => 'Indicador test',
            'decimales' => 2,
            'year_id' => $this->year->id,
            'estado' => true,
            'meta_valor' => 80,
            'meta_operador' => '>=',
        ]));
    }

    private function createDataField(MetIndicator $indicator): MetDataField
    {
        return MetDataField::unguarded(fn () => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Campo',
            'variable_key' => 'A',
            'componente' => 'numerador',
            'input_type' => 'mensual',
            'orden' => 0,
        ]));
    }
}
