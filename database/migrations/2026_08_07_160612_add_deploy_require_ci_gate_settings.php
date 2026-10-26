<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Settings Voyager — gate CI opcional antes de deploy (SDD-100).
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
                'key' => 'module.deploy_require_ci_mode',
                'display_name' => 'Gate CI GitHub (precondición deploy)',
                'value' => 'none',
                'details' => '{"default":"none","options":{"none":"Desactivado (solo aviso en panel)","auto":"Bloquear solo deploy automático","all":"Bloquear automático y manual"},"description":"Exige que el último workflow push de la rama de deploy esté en success antes de backup/pull. Default none = comportamiento post-v4.4.0."}',
                'type' => 'radio_btn',
                'order' => 2,
                'group' => 'Deploy',
            ],
            [
                'key' => 'module.deploy_require_ci_match_sha',
                'display_name' => 'Gate CI: exigir mismo SHA remoto',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Además de success, el head_sha del run debe coincidir con origin/{rama de deploy}."}',
                'type' => 'radio_btn',
                'order' => 3,
                'group' => 'Deploy',
            ],
            [
                'key' => 'module.deploy_require_ci_block_unknown',
                'display_name' => 'Gate CI: bloquear si CI desconocido',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado (estricto)","Off":"Desactivado (aviso)"},"description":"Si On, unknown (sin token/API caída) también bloquea. Recomendado Off."}',
                'type' => 'radio_btn',
                'order' => 4,
                'group' => 'Deploy',
            ],
        ];

        foreach ($rows as $row) {
            if (DB::table('settings')->where('key', $row['key'])->exists()) {
                DB::table('settings')
                    ->where('key', $row['key'])
                    ->update([
                        'display_name' => $row['display_name'],
                        'details' => $row['details'],
                        'type' => $row['type'],
                        'order' => $row['order'],
                        'group' => $row['group'],
                    ]);

                continue;
            }

            DB::table('settings')->insert($row);
        }

        // Empujar el resto de settings Deploy hacia abajo si quedaron con order bajo.
        DB::table('settings')
            ->where('group', 'Deploy')
            ->where('key', 'services.deploy_branch')
            ->update(['order' => 5]);
        DB::table('settings')
            ->where('group', 'Deploy')
            ->where('key', 'services.deploy_remote')
            ->update(['order' => 6]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', [
            'module.deploy_require_ci_mode',
            'module.deploy_require_ci_match_sha',
            'module.deploy_require_ci_block_unknown',
        ])->delete();
    }
};
