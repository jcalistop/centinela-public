<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE_NAME = 'indicator_ambitos';

    /** @var list<string> */
    private const ROLES_WITH_DEFAULT_ACCESS = ['admin', 'parametrizador'];

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $this->ensurePermissions();
        $this->assignPermissionsToRoles();
    }

    public function down(): void
    {
        //
    }

    private function ensurePermissions(): void
    {
        foreach ($this->permissionKeys() as $key) {
            $exists = DB::table('permissions')
                ->where('key', $key)
                ->where('table_name', self::TABLE_NAME)
                ->exists();

            if (! $exists) {
                DB::table('permissions')->insert([
                    'key' => $key,
                    'table_name' => self::TABLE_NAME,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function assignPermissionsToRoles(): void
    {
        $permIds = DB::table('permissions')
            ->whereIn('key', $this->permissionKeys())
            ->where('table_name', self::TABLE_NAME)
            ->pluck('id');

        if ($permIds->isEmpty()) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', self::ROLES_WITH_DEFAULT_ACCESS)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permIds as $permId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function permissionKeys(): array
    {
        return [
            'browse_'.self::TABLE_NAME,
            'read_'.self::TABLE_NAME,
            'edit_'.self::TABLE_NAME,
            'add_'.self::TABLE_NAME,
            'delete_'.self::TABLE_NAME,
        ];
    }
};
