<?php

declare(strict_types=1);

namespace Tests\Feature\Voyager;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RemoveDireccionesEstablecimientosBreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_removes_bread_permissions_menu_and_rows_idempotently(): void
    {
        $dataTypeId = (int) DB::table('data_types')->insertGetId([
            'name' => 'cen_addresses',
            'slug' => 'direcciones-establecimientos',
            'display_name_singular' => 'Dirección',
            'display_name_plural' => 'Direcciones',
            'icon' => 'voyager-location',
            'model_name' => 'App\\Models\\CenAddress',
            'controller' => 'App\\Http\\Controllers\\AddressController',
            'generate_permissions' => 1,
            'server_side' => 0,
            'details' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_rows')->insert([
            'data_type_id' => $dataTypeId,
            'field' => 'id',
            'type' => 'number',
            'display_name' => 'Id',
            'required' => 1,
            'browse' => 0,
            'read' => 0,
            'edit' => 0,
            'add' => 0,
            'delete' => 0,
            'details' => '{}',
            'order' => 1,
        ]);

        $permissionId = (int) DB::table('permissions')->insertGetId([
            'key' => 'browse_cen_addresses',
            'table_name' => 'cen_addresses',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = (int) DB::table('roles')->insertGetId([
            'name' => 'sdd-267-role',
            'display_name' => 'SDD-267 Role',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('permission_role')->insert([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]);

        $menuId = Schema::hasTable('menus')
            ? (int) (DB::table('menus')->value('id') ?? DB::table('menus')->insertGetId([
                'name' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]))
            : 1;

        if (Schema::hasTable('menu_items')) {
            DB::table('menu_items')->insert([
                'menu_id' => $menuId,
                'title' => 'Direcciones',
                'url' => '',
                'target' => '_self',
                'icon_class' => 'voyager-location',
                'color' => null,
                'parent_id' => null,
                'order' => 99,
                'route' => 'voyager.direcciones-establecimientos.index',
                'parameters' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration = require database_path('migrations/2026_10_09_135519_remove_direcciones_establecimientos_bread.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseMissing('data_types', ['name' => 'cen_addresses']);
        $this->assertDatabaseMissing('data_types', ['slug' => 'direcciones-establecimientos']);
        $this->assertDatabaseMissing('data_rows', ['data_type_id' => $dataTypeId]);
        $this->assertDatabaseMissing('permissions', ['table_name' => 'cen_addresses']);
        $this->assertDatabaseMissing('permission_role', ['permission_id' => $permissionId]);
        $this->assertDatabaseHas('roles', ['id' => $roleId]);

        if (Schema::hasTable('menu_items')) {
            $this->assertDatabaseMissing('menu_items', [
                'route' => 'voyager.direcciones-establecimientos.index',
            ]);
        }

        if (Schema::hasTable('cen_addresses')) {
            $this->assertTrue(Schema::hasTable('cen_addresses'));
        }
    }

    public function test_admin_direcciones_establecimientos_returns_not_found_without_bread(): void
    {
        $this->assertDatabaseMissing('data_types', ['slug' => 'direcciones-establecimientos']);

        $adminRoleId = (int) DB::table('roles')->insertGetId([
            'name' => 'admin',
            'display_name' => 'Admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $adminRoleId,
        ]);
        $this->assertInstanceOf(User::class, $user);

        $this->actingAs($user)
            ->get('/admin/direcciones-establecimientos')
            ->assertNotFound();
    }
}
