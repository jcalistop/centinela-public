<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BREAD Voyager para `indicator_types`: slug de URL y controlador Centinela.
     *
     * Tras esto, las rutas las registra Voyager (resource bajo /admin/{slug}).
     * Actualizar enlaces en menús Voyager que apunten al slug anterior, si existían.
     */
    public function up(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        DB::table('data_types')
            ->where('name', 'indicator_types')
            ->update([
                'slug' => 'instrumentos-evaluacion',
                'controller' => 'App\\Http\\Controllers\\Admin\\IndicatorTypeCrudController',
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        DB::table('data_types')
            ->where('name', 'indicator_types')
            ->update([
                'slug' => 'indicator-types',
                'controller' => null,
            ]);
    }
};
