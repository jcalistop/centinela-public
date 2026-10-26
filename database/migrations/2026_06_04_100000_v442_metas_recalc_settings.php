<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-109 — recálculo incremental Metas: variables CENTINELA_METAS_RECALC_* → settings Voyager.
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
                'key' => 'module.metas_recalc_scope_enabled',
                'display_name' => 'Metas — recálculo automático en cola (SDD-109)',
                'value' => $this->envToOnOff('CENTINELA_METAS_RECALC_SCOPE_ENABLED', 'true'),
                'details' => '{"default":"On","options":{"On":"Activado","Off":"Desactivado"},"description":"Tras guardar carga o configuración en AUTOEVALUACIÓN, encola recálculo acotado por indicador/centro. Requiere worker de cola activo."}',
                'type' => 'radio_btn',
                'order' => 15,
                'group' => 'Modulos',
            ],
            [
                'key' => 'system.metas_recalc_debounce_seconds',
                'display_name' => 'Metas — debounce recálculo (segundos)',
                'value' => (string) max(10, (int) env('CENTINELA_METAS_RECALC_DEBOUNCE', 90)),
                'details' => '{"min":10,"max":600,"step":10,"description":"Ventana de agrupación antes de ejecutar el job de recálculo por indicador/centro."}',
                'type' => 'text',
                'order' => 27,
                'group' => 'Sistema',
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
            'module.metas_recalc_scope_enabled',
            'system.metas_recalc_debounce_seconds',
        ])->delete();
    }

    private function envToOnOff(string $envKey, string $default): string
    {
        $value = env($envKey, $default);
        if (is_bool($value)) {
            return $value ? 'On' : 'Off';
        }

        $lower = strtolower((string) $value);

        return in_array($lower, ['true', '1', 'on'], true) ? 'On' : 'Off';
    }
};
