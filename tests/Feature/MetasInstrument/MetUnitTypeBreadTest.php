<?php

namespace Tests\Feature\MetasInstrument;

use App\Http\Controllers\Admin\MetUnitTypeCrudController;
use App\Livewire\MetasInstrument\MeasurementUnitsManagerComponent;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetMeasurementUnit;
use App\Models\MetUnitType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class MetUnitTypeBreadTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    private Year $year;

    private CenCenter $center;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn (): ?bool => true);

        Route::get('admin/unidades-metas', fn (): string => 'ok')
            ->name('voyager.unidades-metas.index');
        $this->app['router']->getRoutes()->refreshNameLookups();

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

        $this->center = $this->createMinimalCenCenter([
            'codigo' => 'CEN-20707',
            'cen_hie_dependency_id' => 1,
        ]);

        $this->actingAs($this->admin)->withSession(['indicator_year' => $this->year]);
    }

    public function test_destroy_blocks_deletion_when_unit_type_is_in_use(): void
    {
        $unitType = MetUnitType::create([
            'codigo' => 'T-URG',
            'nombre' => 'Urgencia',
            'orden' => 1,
            'enabled' => true,
        ]);

        MetMeasurementUnit::unguarded(fn () => MetMeasurementUnit::create([
            'cen_center_id' => $this->center->id,
            'year_id' => $this->year->id,
            'met_unit_type_id' => $unitType->id,
            'codigo' => 'U-A',
            'nombre' => 'Unidad A',
            'activo' => true,
            'cen_hie_dependency_id' => 1,
        ]));

        $request = Request::create('/admin/unidades-metas/'.$unitType->id, 'DELETE');
        $response = app(MetUnitTypeCrudController::class)->destroy($request, $unitType->id);

        $this->assertSame('error', $response->getSession()->get('alert-type'));
        $this->assertStringContainsString('en uso', (string) $response->getSession()->get('message'));
        $this->assertDatabaseHas('met_unit_types', ['id' => $unitType->id]);
    }

    public function test_destroy_allows_deletion_when_unit_type_is_not_in_use(): void
    {
        $unitType = MetUnitType::create([
            'codigo' => 'T-PAB',
            'nombre' => 'Pabellón',
            'orden' => 2,
            'enabled' => true,
        ]);

        $request = Request::create('/admin/unidades-metas/'.$unitType->id, 'DELETE');
        $response = app(MetUnitTypeCrudController::class)->destroy($request, $unitType->id);

        $this->assertSame('success', $response->getSession()->get('alert-type'));
        $this->assertDatabaseMissing('met_unit_types', ['id' => $unitType->id]);
    }

    public function test_disabled_unit_types_are_hidden_from_new_unit_selector(): void
    {
        $indicatorType = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'metas-20707-test',
            'nombre_indicador' => 'Metas 20.707 Test',
            'route' => 'metas-20707-test',
            'view' => null,
            'estado' => true,
            'orden' => 99,
            'measurement_granularity' => 'internal_unit',
        ]));

        $enabled = MetUnitType::create(['codigo' => 'T-EN', 'nombre' => 'Habilitado', 'orden' => 1, 'enabled' => true]);
        $disabled = MetUnitType::create(['codigo' => 'T-DIS', 'nombre' => 'Deshabilitado', 'orden' => 2, 'enabled' => false]);

        $component = Livewire::test(MeasurementUnitsManagerComponent::class, [
            'centerId' => $this->center->id,
            'indicatorTypeId' => $indicatorType->id,
        ]);

        $unitTypeIds = collect($component->viewData('unitTypes'))->pluck('id')->all();

        $this->assertContains($enabled->id, $unitTypeIds);
        $this->assertNotContains($disabled->id, $unitTypeIds);
    }
}
