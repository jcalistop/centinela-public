<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-089: ítem de menú Voyager para BREAD Ámbitos de indicadores (idempotente).
 */
return new class extends Migration
{
    private const MENU_NAME = 'admin';

    private const ROUTE = 'voyager.ambitos-indicadores.index';

    private const TITLE = 'Ámbitos de indicadores';

    private const ICON = 'voyager-tag';

    private const PARENT_TITLES = ['Parametrización', 'Configuración'];

    private const TOOLS_TITLES = ['Herramientas', 'Tools'];

    public function up(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items') || ! Schema::hasColumn('menu_items', 'route')) {
            return;
        }

        if (! Schema::hasTable('data_types')
            || ! DB::table('data_types')->where('slug', 'ambitos-indicadores')->exists()) {
            return;
        }

        $menuId = DB::table('menus')->where('name', self::MENU_NAME)->value('id');
        if ($menuId === null) {
            return;
        }

        $parentId = $this->resolveParentId((int) $menuId);
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

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        DB::table('menu_items')
            ->where('route', self::ROUTE)
            ->delete();
    }

    private function resolveParentId(int $menuId): ?int
    {
        $viaRoles = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('route', 'voyager.roles.index')
            ->value('parent_id');

        if ($viaRoles !== null) {
            return (int) $viaRoles;
        }

        $parametrizacionId = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->whereIn('title', self::PARENT_TITLES)
            ->orderByRaw('CASE WHEN title = ? THEN 0 ELSE 1 END', [self::PARENT_TITLES[0]])
            ->value('id');

        if ($parametrizacionId !== null) {
            return (int) $parametrizacionId;
        }

        $toolsId = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->whereIn('title', self::TOOLS_TITLES)
            ->orderByRaw('CASE WHEN title = ? THEN 0 ELSE 1 END', [self::TOOLS_TITLES[0]])
            ->value('id');

        if ($toolsId !== null) {
            return (int) $toolsId;
        }

        return DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('title', 'like', '%Config%')
            ->value('id');
    }
};
