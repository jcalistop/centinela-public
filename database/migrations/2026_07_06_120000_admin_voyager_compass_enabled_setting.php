<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Setting admin.voyager_compass_enabled → config voyager.compass_in_production (SettingsBridge).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $row = [
            'key' => 'admin.voyager_compass_enabled',
            'display_name' => 'Habilitar Compass de Voyager',
            'value' => 'Off',
            'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Permite acceder a /admin/compass fuera del entorno local (herramientas Artisan, logs y caché de Voyager)."}',
            'type' => 'radio_btn',
            'order' => 9,
            'group' => 'Admin',
        ];

        if (DB::table('settings')->where('key', $row['key'])->exists()) {
            DB::table('settings')
                ->where('key', $row['key'])
                ->update([
                    'display_name' => $row['display_name'],
                    'details' => $row['details'],
                    'type' => $row['type'],
                    'group' => $row['group'],
                ]);

            return;
        }

        DB::table('settings')->insert($row);
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->where('key', 'admin.voyager_compass_enabled')->delete();
    }
};
