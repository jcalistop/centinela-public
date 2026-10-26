<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-265 — flag módulo Filament Resource configurar-tipo-indicador (off por defecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $row = [
            'key' => 'module.filament_configurar_tipo_indicador_enabled',
            'display_name' => 'Filament — configurar tipo indicador (T2c)',
            'value' => 'Off',
            'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Habilita /filament/configurar-tipo-indicador (SDD-265). BREAD Voyager sigue operativo."}',
            'type' => 'radio_btn',
            'order' => 43,
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

        DB::table('settings')->where('key', 'module.filament_configurar_tipo_indicador_enabled')->delete();
    }
};
