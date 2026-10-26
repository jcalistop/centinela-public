<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('indicator_type_settings')
            ->where('etapa_vigente', 'ANUAL')
            ->update(['etapa_vigente' => 'CERRADO']);
    }

    public function down(): void
    {
        // No se restaura ANUAL: el valor dejó de ser etapa vigente válida (equivalente operativo = CERRADO).
    }
};
