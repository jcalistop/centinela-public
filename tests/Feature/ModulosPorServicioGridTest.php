<?php

namespace Tests\Feature;

use App\Livewire\ModulosPorServicio\Grid;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use TCG\Voyager\Models\Permission;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class ModulosPorServicioGridTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CenHieDependency $ss;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        foreach (['browse_service_module_toggles', 'edit_service_module_toggles'] as $key) {
            $perm = Permission::firstOrCreate(
                ['key' => $key, 'table_name' => null],
                ['key' => $key, 'table_name' => null],
            );
            $adminRole->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $this->ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);

        IndicatorType::unguarded(fn () => IndicatorType::updateOrCreate(
            ['route' => 'adp'],
            [
                'clave_indicador' => 'adp',
                'nombre_indicador' => 'ADP',
                'estado' => false,
                'orden' => 1,
            ]
        ));
    }

    public function test_catalog_off_marks_module_disabled_and_blocks_toggle_mutation(): void
    {
        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'adp',
            'enabled' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(Grid::class)
            ->assertSet('catalogDisabledModules.adp', true)
            ->assertSee('deshabilitados en el catálogo')
            ->call('toggle', $this->ss->id, 'adp')
            ->assertForbidden();

        $this->assertDatabaseHas('service_module_toggles', [
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'adp',
            'enabled' => 1,
        ]);
    }

    public function test_catalog_on_allows_toggle_mutation(): void
    {
        IndicatorType::unguarded(fn () => IndicatorType::where('route', 'adp')->update(['estado' => true]));

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'adp',
            'enabled' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(Grid::class)
            ->assertSet('catalogDisabledModules.adp', null)
            ->call('toggle', $this->ss->id, 'adp')
            ->assertSet('toggles.'.$this->ss->id.'.adp', false);

        $this->assertDatabaseHas('service_module_toggles', [
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'adp',
            'enabled' => 0,
        ]);
    }
}
