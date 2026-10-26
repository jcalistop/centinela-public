<?php

namespace Tests\Unit\Models;

use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CenHieDependencyTipoServicioScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        CenHieDependency::unguarded(fn () => CenHieDependency::query()->insert([
            ['id' => 1, 'codigo' => 'SS01', 'nombre' => 'SS Uno', 'tipo_dependencia' => 'SERVICIO', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'codigo' => 'SS02', 'nombre' => 'SS Dos', 'tipo_dependencia' => 'SERVICIO', 'created_at' => $now, 'updated_at' => $now],
        ]));
    }

    public function test_admin_sees_all_servicios_in_tipo_servicio_scope(): void
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);

        $this->actingAs($admin);

        $this->assertSame(2, CenHieDependency::query()->tipoServicio()->count());
    }

    public function test_control_gestion_sees_only_own_servicio_in_tipo_servicio_scope(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $cgRole = Role::query()->firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $cg = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 2]);

        $this->actingAs($cg);

        $ids = CenHieDependency::query()->tipoServicio()->pluck('id')->all();
        $this->assertSame([2], $ids);
    }

    public function test_admin_enabled_centinela_ss_lists_all_servicios_regardless_of_habilitado(): void
    {
        // SS 2 deshabilitado; admin lo ve igual
        CenHieDependency::query()->where('id', 2)->update(['habilitado' => false]);

        $adminRole = Role::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 1]);

        $this->actingAs($admin);

        $this->assertSame(2, CenHieDependency::query()->enabledCentinelaSs()->count());
    }
}
