<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-264 — flag módulo Filament Resource establecimientos (off por defecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $row = [
            'key' => 'module.filament_establecimientos_enabled',
            'display_name' => 'Filament — establecimientos (T2b)',
            'value' => 'Off',
            'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Habilita /filament/establecimientos (SDD-264). BREAD Voyager sigue operativo."}',
            'type' => 'radio_btn',
            'order' => 42,
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

        DB::table('settings')->where('key', 'module.filament_establecimientos_enabled')->delete();
    }
};
