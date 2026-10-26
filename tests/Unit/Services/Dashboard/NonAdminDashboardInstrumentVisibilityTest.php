<?php

namespace Tests\Unit\Services\Dashboard;

use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\Dashboard\NonAdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NonAdminDashboardInstrumentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_instrument_widgets_require_ss_toggle_and_year_habilitado(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        $comgesType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'voyager.compromisos.index'],
            [
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'estado' => true,
                'orden' => 1,
            ],
        ));

        $metasType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'metas-18834'],
            [
                'clave_indicador' => 'metas-18834',
                'nombre_indicador' => 'Metas 18834',
                'estado' => false,
                'orden' => 2,
            ],
        ));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'comges',
            'enabled' => true,
        ]));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $comgesType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'habilitado' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $metasType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'habilitado' => false,
        ]));

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $instrumentos = app(NonAdminDashboardService::class)->forUser($user)['instrumentos'] ?? [];
        $labels = array_column($instrumentos, 'label');

        $this->assertContains('COMGES', $labels);
        $this->assertNotContains('Metas 18834', $labels);
    }

    public function test_instrument_widgets_exclude_module_when_ss_toggle_disabled(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();

        $metasType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['route' => 'metas-18834'],
            [
                'clave_indicador' => 'metas-18834',
                'nombre_indicador' => 'Metas 18834',
                'estado' => true,
                'orden' => 1,
            ],
        ));

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'metas-18834',
            'enabled' => false,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $metasType->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => 1,
            'habilitado' => true,
        ]));

        $this->actingAs($user);
        session(['indicator_year' => $year]);

        $instrumentos = app(NonAdminDashboardService::class)->forUser($user)['instrumentos'] ?? [];
        $labels = array_column($instrumentos, 'label');

        $this->assertNotContains('Metas 18834', $labels);
    }
}
