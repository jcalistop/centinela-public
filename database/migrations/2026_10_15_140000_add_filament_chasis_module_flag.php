<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-284 — flag maestro chasis Filament multi-panel (off por defecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $row = [
            'key' => 'module.filament_enabled',
            'display_name' => 'Filament — chasis multi-panel (maestro)',
            'value' => 'Off',
            'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Habilita paneles Filament (/servicio-salud, /catalogo, /pais, /configuracion). Off redirige a Voyager /admin (SDD-284)."}',
            'type' => 'radio_btn',
            'order' => 39,
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

        DB::table('settings')->where('key', 'module.filament_enabled')->delete();
    }
};
