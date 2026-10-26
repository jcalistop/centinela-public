<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        if (DB::table('settings')->where('key', 'services.national_establishments_enabled')->exists()) {
            DB::table('settings')->where('key', 'services.national_establishments_enabled')->delete();
        }

        $rows = [
            [
                'key' => 'module.national_establishments_enabled',
                'display_name' => 'Sincronización catálogo nacional de establecimientos',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"},"description":"Habilita la sincronización con datos.gob.cl (CKAN DataStore) y el catálogo de establecimientos en Parametrización."}',
                'type' => 'radio_btn',
                'order' => 14,
                'group' => 'Modulos',
            ],
            [
                'key' => 'services.national_establishments_api_base',
                'display_name' => 'URL base API CKAN establecimientos',
                'value' => 'https://datos.gob.cl/api/3/action',
                'details' => '{"description":"Base URL para datastore_search (sin barra final)."}',
                'type' => 'text',
                'order' => 21,
                'group' => 'Servicios externos',
            ],
            [
                'key' => 'services.national_establishments_resource_id',
                'display_name' => 'Resource ID CKAN establecimientos',
                'value' => '2c44d782-3365-44e3-aefb-2c8b8363a1bc',
                'details' => '{}',
                'type' => 'text',
                'order' => 22,
                'group' => 'Servicios externos',
            ],
            [
                'key' => 'system.national_establishments_page_size',
                'display_name' => 'Tamaño de página sync establecimientos',
                'value' => '500',
                'details' => '{"min":50,"max":1000,"step":50}',
                'type' => 'text',
                'order' => 25,
                'group' => 'Sistema',
            ],
            [
                'key' => 'system.national_establishments_timeout',
                'display_name' => 'Timeout HTTP sync establecimientos (seg)',
                'value' => '60',
                'details' => '{"min":10,"max":300,"step":5}',
                'type' => 'text',
                'order' => 26,
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
            'module.national_establishments_enabled',
            'services.national_establishments_enabled',
            'services.national_establishments_api_base',
            'services.national_establishments_resource_id',
            'system.national_establishments_page_size',
            'system.national_establishments_timeout',
        ])->delete();
    }
};
