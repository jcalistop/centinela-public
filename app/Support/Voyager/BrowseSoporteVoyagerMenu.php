<?php

namespace App\Support\Voyager;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Facades\Voyager;

/**
 * Permiso Voyager `browse_soporte`: visibilidad del ítem de menú bajo `/admin/soporte`.
 */
final class BrowseSoporteVoyagerMenu
{
    /** @var array<int, string> */
    private const PLATFORM_ROLES_WITH_MENU = [
        'admin',
        'control-gestion',
        'referente',
        'visualizador',
    ];

    /**
     * Garantiza el permiso y las asignaciones a roles conocidos / con `browse_admin`, excluyendo `parametrizador`.
     *
     * Idempotente y segura ante roles creados después de la primera migración (p. ej. tests o nuevos registros Voyager).
     */
    public static function syncRoleGrants(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role') || ! Schema::hasTable('roles')) {
            return;
        }

        $permissionModel = Voyager::model('Permission');
        /** @var Model $permission */
        $permission = $permissionModel::query()->firstOrCreate(
            ['key' => 'browse_soporte'],
            [
                'table_name' => 'soporte',
            ]
        );
        $permId = (int) $permission->getKey();

        /** @var array<int, int> $roleIds */
        $roleIds = [];

        $browseAdminId = $permissionModel::query()->where('key', 'browse_admin')->value('id');
        if ($browseAdminId !== null) {
            $roleIds = DB::table('permission_role')
                ->join('roles', 'roles.id', '=', 'permission_role.role_id')
                ->where('permission_role.permission_id', (int) $browseAdminId)
                ->pluck('roles.id')
                ->all();
        }

        $explicitIds = DB::table('roles')
            ->whereIn('name', self::PLATFORM_ROLES_WITH_MENU)
            ->pluck('id')
            ->all();

        $roleIds = array_values(array_unique(array_merge(
            array_map(static fn ($id): int => (int) $id, $roleIds),
            array_map(static fn ($id): int => (int) $id, $explicitIds),
        )));

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                [
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                ],
            ]);
        }

        foreach (['parametrizador'] as $excludedRoleName) {
            $excludedId = DB::table('roles')->where('name', $excludedRoleName)->value('id');
            if ($excludedId !== null) {
                DB::table('permission_role')
                    ->where('role_id', (int) $excludedId)
                    ->where('permission_id', $permId)
                    ->delete();
            }
        }

        Cache::forget('voyager_menu_admin');
    }
}
