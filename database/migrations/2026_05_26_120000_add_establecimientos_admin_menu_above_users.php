<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ítem raíz «Establecimientos» en el menú admin, inmediatamente antes de «Usuarios».
 */
return new class extends Migration
{
    private const MENU_NAME = 'admin';

    private const ROUTE = 'voyager.establecimientos.index';

    public function up(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items')) {
            return;
        }

        if (! Schema::hasColumn('menu_items', 'route')) {
            return;
        }

        $menuId = (int) DB::table('menus')->where('name', self::MENU_NAME)->value('id');

        if ($menuId === 0) {
            return;
        }

        $usersItem = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->whereNull('parent_id')
            ->where(function ($query): void {
                $query->where('route', 'voyager.users.index')
                    ->orWhereIn('title', ['Usuarios', 'Users']);
            })
            ->orderBy('id')
            ->first(['id', 'order']);

        $targetOrder = $usersItem !== null
            ? max(1, (int) $usersItem->order - 1)
            : 10;

        $matchingIds = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('route', self::ROUTE)
            ->orderBy('id')
            ->pluck('id');

        if ($matchingIds->count() > 1) {
            DB::table('menu_items')
                ->whereIn('id', $matchingIds->slice(1)->all())
                ->delete();
        }

        $attributes = [
            'menu_id' => $menuId,
            'title' => 'Establecimientos',
            'url' => '',
            'target' => '_self',
            'icon_class' => 'voyager-shop',
            'color' => '',
            'parent_id' => null,
            'order' => $targetOrder,
            'route' => self::ROUTE,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('menu_items', 'parameters')) {
            $attributes['parameters'] = '';
        }

        $keepId = $matchingIds->first();

        if ($keepId !== null) {
            DB::table('menu_items')->where('id', $keepId)->update($attributes);

            return;
        }

        $attributes['created_at'] = now();

        DB::table('menu_items')->insert($attributes);
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items') || ! Schema::hasColumn('menu_items', 'route')) {
            return;
        }

        DB::table('menu_items')->where('route', self::ROUTE)->delete();
    }
};
