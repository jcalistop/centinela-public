<?php

use Database\Seeders\IndicatorAmbitoSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('indicator_ambitos')) {
            return;
        }

        (new IndicatorAmbitoSeeder)->run();

        if (! Schema::hasTable('data_types')) {
            return;
        }

        if (DB::table('data_types')->where('slug', 'ambitos-indicadores')->exists()) {
            return;
        }

        $now = now();
        $details = json_encode([
            'order_column' => 'orden',
            'order_display_column' => 'nombre',
            'order_direction' => 'asc',
            'default_search_key' => 'nombre',
        ], JSON_THROW_ON_ERROR);

        $dataTypeId = DB::table('data_types')->insertGetId([
            'name' => 'indicator_ambitos',
            'slug' => 'ambitos-indicadores',
            'display_name_singular' => 'Ámbito de indicador',
            'display_name_plural' => 'Ámbitos de indicadores',
            'icon' => 'voyager-tag',
            'model_name' => 'App\\Models\\IndicatorAmbito',
            'policy_name' => 'App\\Policies\\IndicatorAmbitoPolicy',
            'controller' => null,
            'description' => 'Taxonomía transversal para agrupar indicadores COMGES y Metas (SDD-089).',
            'generate_permissions' => 1,
            'server_side' => 0,
            'details' => $details,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (! Schema::hasTable('data_rows')) {
            return;
        }

        $rows = [
            ['field' => 'id', 'type' => 'number', 'display_name' => 'ID', 'browse' => 0, 'read' => 1, 'edit' => 0, 'add' => 0, 'order' => 1],
            ['field' => 'nombre', 'type' => 'text', 'display_name' => 'Nombre', 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'order' => 2],
            ['field' => 'slug', 'type' => 'text', 'display_name' => 'Slug', 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'order' => 3],
            ['field' => 'orden', 'type' => 'number', 'display_name' => 'Orden', 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'order' => 4],
            ['field' => 'estado', 'type' => 'checkbox', 'display_name' => 'Activo', 'browse' => 1, 'read' => 1, 'edit' => 1, 'add' => 1, 'order' => 5],
            ['field' => 'created_at', 'type' => 'timestamp', 'display_name' => 'Creado', 'browse' => 0, 'read' => 1, 'edit' => 0, 'add' => 0, 'order' => 6],
            ['field' => 'updated_at', 'type' => 'timestamp', 'display_name' => 'Actualizado', 'browse' => 0, 'read' => 1, 'edit' => 0, 'add' => 0, 'order' => 7],
        ];

        foreach ($rows as $row) {
            DB::table('data_rows')->insert([
                'data_type_id' => $dataTypeId,
                'field' => $row['field'],
                'type' => $row['type'],
                'display_name' => $row['display_name'],
                'required' => in_array($row['field'], ['nombre'], true) ? 1 : 0,
                'browse' => $row['browse'],
                'read' => $row['read'],
                'edit' => $row['edit'],
                'add' => $row['add'],
                'delete' => 0,
                'details' => '{}',
                'order' => $row['order'],
            ]);
        }

        if (Schema::hasTable('menu_items')) {
            $toolsMenuId = DB::table('menu_items')
                ->where('title', 'Herramientas')
                ->orWhere('title', 'Tools')
                ->value('id');

            if ($toolsMenuId === null) {
                $toolsMenuId = DB::table('menu_items')->where('title', 'like', '%Config%')->value('id');
            }

            if ($toolsMenuId !== null && ! DB::table('menu_items')->where('route', 'voyager.ambitos-indicadores.index')->exists()) {
                $maxOrder = (int) DB::table('menu_items')->where('parent_id', $toolsMenuId)->max('order');

                DB::table('menu_items')->insert([
                    'menu_id' => 1,
                    'title' => 'Ámbitos de indicadores',
                    'url' => '',
                    'target' => '_self',
                    'icon_class' => 'voyager-tag',
                    'color' => null,
                    'parent_id' => $toolsMenuId,
                    'order' => $maxOrder + 1,
                    'route' => 'voyager.ambitos-indicadores.index',
                    'parameters' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
