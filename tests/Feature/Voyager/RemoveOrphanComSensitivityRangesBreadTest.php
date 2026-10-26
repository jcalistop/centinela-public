<?php

declare(strict_types=1);

namespace Tests\Feature\Voyager;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RemoveOrphanComSensitivityRangesBreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_removes_orphan_bread_permissions_and_rows(): void
    {
        $dataTypeId = (int) DB::table('data_types')->insertGetId([
            'name' => 'com_sensitivity_ranges',
            'slug' => 'tabla-sensibilidad',
            'display_name_singular' => 'Rango de sensibilidad',
            'display_name_plural' => 'Rangos de sensibilidad',
            'icon' => 'voyager-window-list',
            'model_name' => 'App\\Models\\ComSensitivityRange',
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
            'key' => 'browse_com_sensitivity_ranges',
            'table_name' => 'com_sensitivity_ranges',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = (int) DB::table('roles')->insertGetId([
            'name' => 'orphan-bread-role',
            'display_name' => 'Orphan Bread Role',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('permission_role')->insert([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]);

        $migration = require database_path('migrations/2026_10_08_223515_remove_orphan_com_sensitivity_ranges_bread.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseMissing('data_types', ['name' => 'com_sensitivity_ranges']);
        $this->assertDatabaseMissing('data_types', ['slug' => 'tabla-sensibilidad']);
        $this->assertDatabaseMissing('data_rows', ['data_type_id' => $dataTypeId]);
        $this->assertDatabaseMissing('permissions', ['table_name' => 'com_sensitivity_ranges']);
        $this->assertDatabaseMissing('permission_role', ['permission_id' => $permissionId]);
        $this->assertDatabaseHas('roles', ['id' => $roleId]);
    }
}
