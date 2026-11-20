<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-338 — identidad de instancia (branding) en settings.
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
                'key' => 'instance.ss_display_name',
                'display_name' => 'Nombre del Servicio de Salud (instancia)',
                'value' => 'SS de prueba',
                'details' => '{"description":"Nombre público del SS operador de esta instalación (footers, copy corto, emails). No sustituye el SS de tenancy del usuario."}',
                'type' => 'text',
                'order' => 1,
                'group' => 'Servicio de Salud',
            ],
            [
                'key' => 'instance.ss_url',
                'display_name' => 'URL institucional del SS',
                'value' => '',
                'details' => '{"description":"URL https del sitio institucional. Vacío = mostrar solo el nombre sin enlace."}',
                'type' => 'text',
                'order' => 2,
                'group' => 'Servicio de Salud',
            ],
            [
                'key' => 'instance.unit_name',
                'display_name' => 'Unidad / departamento',
                'value' => 'Departamento de Control de Gestión',
                'details' => '{"description":"Unidad / departamento que aparece en el pie de página."}',
                'type' => 'text',
                'order' => 3,
                'group' => 'Servicio de Salud',
            ],
        ];

        foreach ($rows as $row) {
            if (DB::table('settings')->where('key', $row['key'])->exists()) {
                continue;
            }

            DB::table('settings')->insert($row);
        }

        DB::table('settings')
            ->where('group', 'Instancia')
            ->whereIn('key', [
                'instance.ss_display_name',
                'instance.ss_url',
                'instance.unit_name',
            ])
            ->update(['group' => 'Servicio de Salud']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', [
            'instance.ss_display_name',
            'instance.ss_url',
            'instance.unit_name',
        ])->delete();
    }
};
