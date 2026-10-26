<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-261 — flag módulo Filament settings (off por defecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $row = [
            'key' => 'module.filament_settings_enabled',
            'display_name' => 'Filament — panel configuración (piloto T1)',
            'value' => 'Off',
            'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Habilita /filament/settings (SDD-261). Voyager SettingsManager sigue operativo."}',
            'type' => 'radio_btn',
            'order' => 40,
            'group' => 'Modulos',
        ];

        if (DB::table('settings')->where('key', $row['key'])->exists()) {
            return;
        }

        DB::table('settings')->insert($row);
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->where('key', 'module.filament_settings_enabled')->delete();
    }
};
