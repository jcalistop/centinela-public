<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-262 — flag módulo Filament Resource instrumentos-evaluacion (off por defecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $row = [
            'key' => 'module.filament_instrumentos_evaluacion_enabled',
            'display_name' => 'Filament — instrumentos de evaluación (T2a)',
            'value' => 'Off',
            'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Habilita /filament/instrumentos-evaluacion (SDD-262). BREAD Voyager sigue operativo."}',
            'type' => 'radio_btn',
            'order' => 41,
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

        DB::table('settings')->where('key', 'module.filament_instrumentos_evaluacion_enabled')->delete();
    }
};
