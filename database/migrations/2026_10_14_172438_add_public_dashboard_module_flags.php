<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-282 — flags kill-switch dash público y vitrina SS (off por defecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $rows = [
            [
                'key' => 'module.public_dashboard_enabled',
                'display_name' => 'Dashboard público (guest)',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Kill-switch del hub guest (SDD-282). Off redirige / y /publico/* al login."}',
                'type' => 'radio_btn',
                'order' => 44,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.public_ss_dashboard_enabled',
                'display_name' => 'Dashboard público — vitrina SS',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Muestra la sección de Servicios de Salud en el hub guest (SDD-282). Solo aplica si el dashboard público está On."}',
                'type' => 'radio_btn',
                'order' => 45,
                'group' => 'Modulos',
            ],
        ];

        foreach ($rows as $row) {
            if (DB::table('settings')->where('key', $row['key'])->exists()) {
                continue;
            }

            DB::table('settings')->insert($row);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', [
            'module.public_dashboard_enabled',
            'module.public_ss_dashboard_enabled',
        ])->delete();
    }
};
