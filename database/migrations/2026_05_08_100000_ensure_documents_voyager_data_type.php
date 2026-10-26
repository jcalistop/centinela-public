<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Facades\Voyager;

/**
 * Garantiza fila BREAD de Documentos para entornos migrados sin dump SQL (p. ej. PHPUnit),
 * de modo que {@see Voyager::routes()} registre admin/documentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        if (DB::table('data_types')->where('model_name', 'App\\Models\\Document')->exists()) {
            return;
        }

        $now = now();
        $details = json_encode([
            'order_column' => null,
            'order_display_column' => null,
            'order_direction' => 'asc',
            'default_search_key' => 'nombre',
            'scope' => 'filter',
        ], JSON_THROW_ON_ERROR);

        $id = DB::table('data_types')->insertGetId([
            'name' => 'documents',
            'slug' => 'documentos',
            'display_name_singular' => 'Documento',
            'display_name_plural' => 'Documentos',
            'icon' => 'voyager-book',
            'model_name' => 'App\\Models\\Document',
            'policy_name' => null,
            'controller' => 'App\\Http\\Controllers\\Voyager\\DocumentController',
            'description' => null,
            'generate_permissions' => 0,
            'server_side' => 0,
            'details' => $details,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (! Schema::hasTable('data_rows')) {
            return;
        }

        DB::table('data_rows')->insert([
            'data_type_id' => $id,
            'field' => 'nombre',
            'type' => 'text',
            'display_name' => 'Nombre',
            'required' => 0,
            'browse' => 1,
            'read' => 1,
            'edit' => 1,
            'add' => 1,
            'delete' => 0,
            'details' => '{}',
            'order' => 1,
        ]);
    }

    /**
     * No eliminar el BREAD en rollback: puede existir en producción seeded manualmente.
     */
    public function down(): void
    {
        //
    }
};
