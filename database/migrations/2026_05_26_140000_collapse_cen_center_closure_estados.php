<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cen_centers') || ! Schema::hasColumn('cen_centers', 'estado_funcionamiento')) {
            return;
        }

        $closureValues = [
            'Cierre Definitivo',
            'Cierre Temporal',
            'CIERRE DEFINITIVO',
            'CIERRE TEMPORAL',
        ];

        DB::table('cen_centers')
            ->whereIn('estado_funcionamiento', $closureValues)
            ->update(['estado_funcionamiento' => 'Cerrado']);
    }

    public function down(): void
    {
        // No reversible: no se distingue cierre temporal vs definitivo tras colapso.
    }
};
