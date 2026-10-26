<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-282 — ítem Voyager «Paneles públicos» bajo Parametrización → Filament.
 */
return new class extends Migration
{
    private const MENU_NAME = 'admin';

    private const ROUTE = 'centinela.admin.paneles-publicos';

    private const TITLE = 'Paneles públicos';

    private const ICON = 'voyager-news';

    private const PERMISSION_KEY = 'browse_paneles-publicos';

    /** @var array<int, string> */
    private const ROLE_NAMES = ['admin', 'parametrizador'];

    public function up(): void
    {
        $this->seedPermission();
        $this->seedMenuItem();
        Cache::forget('voyager_menu_admin');
    }

    public function down(): void
    {
        if (Schema::hasTable('menu_items')) {
            DB::table('menu_items')->where('route', self::ROUTE)->delete();
        }

        if (Schema::hasTable('permissions')) {
            $permissionId = DB::table('permissions')->where('key', self::PERMISSION_KEY)->value('id');
            if ($permissionId !== null) {
                if (Schema::hasTable('permission_role')) {
                    DB::table('permission_role')->where('permission_id', (int) $permissionId)->delete();
                }
                DB::table('permissions')->where('id', (int) $permissionId)->delete();
            }
        }

        Cache::forget('voyager_menu_admin');
    }

    private function seedPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('key', self::PERMISSION_KEY)->value('id');
        if ($permissionId === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'key' => self::PERMISSION_KEY,
                'table_name' => 'paneles-publicos',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('permission_role')) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', self::ROLE_NAMES)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => (int) $permissionId,
                'role_id' => (int) $roleId,
            ]);
        }
    }

    private function seedMenuItem(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items') || ! Schema::hasColumn('menu_items', 'route')) {
            return;
        }

        $menuId = DB::table('menus')->where('name', self::MENU_NAME)->value('id');
        if ($menuId === null) {
            return;
        }

        $parentId = $this->resolveParametrizacionId((int) $menuId);
        if ($parentId === null) {
            return;
        }

        $existingId = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('route', self::ROUTE)
            ->value('id');

        if ($existingId !== null) {
            DB::table('menu_items')
                ->where('id', $existingId)
                ->update([
                    'parent_id' => $parentId,
                    'title' => self::TITLE,
                    'icon_class' => self::ICON,
                    'updated_at' => now(),
                ]);

            return;
        }

        $maxOrder = (int) DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('parent_id', $parentId)
            ->max('order');

        $row = [
            'menu_id' => $menuId,
            'title' => self::TITLE,
            'url' => '',
            'target' => '_self',
            'icon_class' => self::ICON,
            'color' => null,
            'parent_id' => $parentId,
            'order' => $maxOrder + 1,
            'route' => self::ROUTE,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('menu_items', 'parameters')) {
            $row['parameters'] = null;
        }

        DB::table('menu_items')->insert($row);
    }

    private function resolveParametrizacionId(int $menuId): ?int
    {
        $viaRoles = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('route', 'voyager.roles.index')
            ->value('parent_id');

        if ($viaRoles !== null) {
            return (int) $viaRoles;
        }

        $id = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->whereIn('title', ['Parametrización', 'Configuración'])
            ->orderByRaw('CASE WHEN title = ? THEN 0 ELSE 1 END', ['Parametrización'])
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
};
