<?php

namespace Tests\Unit\Support;

use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Support\Voyager\AdminMenuFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMenuFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function seedSupportContext(bool $withToggle = true, bool $toggleEnabled = true): User
    {
        Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $role = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $user = User::factory()->create(['role_id' => $role->id, 'cen_hie_dependency_id' => 1]);
        $user->role()->associate($role)->save();

        if ($withToggle) {
            ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
                'cen_hie_dependency_id' => 1,
                'module_key' => 'soporte',
                'enabled' => $toggleEnabled,
            ]));
        }

        return $user;
    }

    public function test_does_not_filter_when_soporte_accessible(): void
    {
        $user = $this->seedSupportContext(withToggle: false);
        $this->actingAs($user);

        $items = [
            ['title' => 'Soporte', 'route' => 'centinela.support.index', 'children' => []],
        ];

        $this->assertSame($items, AdminMenuFilters::hideSupportWhenDisabled($items));
    }

    public function test_removes_soporte_when_service_module_toggle_disabled(): void
    {
        $user = $this->seedSupportContext(withToggle: true, toggleEnabled: false);
        $this->actingAs($user);

        $items = [
            ['title' => 'Otro', 'route' => 'voyager.dashboard', 'children' => []],
            ['title' => 'Soporte', 'route' => 'centinela.support.index', 'children' => []],
        ];

        $out = AdminMenuFilters::hideSupportWhenDisabled($items);

        $this->assertCount(1, $out);
        $this->assertSame('voyager.dashboard', $out[0]['route']);
    }

    public function test_removes_soporte_from_nested_children_when_disabled(): void
    {
        $user = $this->seedSupportContext(withToggle: true, toggleEnabled: false);
        $this->actingAs($user);

        $items = [
            [
                'title' => 'Herramientas',
                'route' => null,
                'children' => [
                    ['title' => 'Soporte', 'route' => 'centinela.support.index', 'children' => []],
                    ['title' => 'Otro', 'route' => 'x', 'children' => []],
                ],
            ],
        ];

        $out = AdminMenuFilters::hideSupportWhenDisabled($items);

        $this->assertCount(1, $out[0]['children']);
        $this->assertSame('x', $out[0]['children'][0]['route']);
    }

    public function test_admin_sees_soporte_even_when_toggle_disabled(): void
    {
        Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);
        $user->role()->associate($adminRole)->save();

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'soporte',
            'enabled' => false,
        ]));

        $this->actingAs($user);

        $items = [
            ['title' => 'Soporte', 'route' => 'centinela.support.index', 'children' => []],
        ];

        $this->assertSame($items, AdminMenuFilters::hideSupportWhenDisabled($items));
    }
}
