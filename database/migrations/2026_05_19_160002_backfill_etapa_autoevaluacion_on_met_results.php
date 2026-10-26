<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('met_results', 'etapa')) {
            return;
        }

        DB::table('met_results')
            ->where(function ($query): void {
                $query->whereNull('etapa')->orWhere('etapa', '');
            })
            ->update(['etapa' => 'AUTOEVALUACION']);

        if (! Schema::hasColumn('met_establishment_results', 'etapa')) {
            return;
        }

        DB::table('met_establishment_results')
            ->where(function ($query): void {
                $query->whereNull('etapa')->orWhere('etapa', '');
            })
            ->update(['etapa' => 'AUTOEVALUACION']);
    }

    public function down(): void
    {
        // Sin reversión: los datos históricos deben permanecer en AUTOEVALUACION.
    }
};
