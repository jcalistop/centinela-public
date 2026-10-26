<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus') || ! Schema::hasTable('menu_items')) {
            return;
        }

        if (! Schema::hasColumn('menu_items', 'route')) {
            return;
        }

        $menuId = DB::table('menus')->where('name', 'admin')->value('id');
        if ($menuId === null) {
            return;
        }

        $exists = DB::table('menu_items')
            ->where('menu_id', $menuId)
            ->where('route', 'centinela.support.index')
            ->exists();

        if ($exists) {
            return;
        }

        $nextOrder = ((int) DB::table('menu_items')->where('menu_id', $menuId)->max('order')) + 1;

        $row = [
            'menu_id' => $menuId,
            'title' => 'Soporte',
            'url' => '',
            'target' => '_self',
            'icon_class' => 'voyager-help',
            'color' => null,
            'parent_id' => null,
            'order' => $nextOrder,
            'route' => 'centinela.support.index',
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
            ->where('route', 'centinela.support.index')
            ->delete();
    }
};
