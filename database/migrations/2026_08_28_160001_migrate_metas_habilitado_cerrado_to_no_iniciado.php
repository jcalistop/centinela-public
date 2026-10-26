<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ambiguous = DB::table('indicator_type_settings')
            ->whereIn('indicator_type_id', function ($query): void {
                $query->select('id')
                    ->from('indicator_types')
                    ->where('route', 'like', 'metas-%');
            })
            ->where('habilitado', false)
            ->where('etapa_vigente', '<>', 'CERRADO')
            ->count();

        if ($ambiguous > 0) {
            throw new RuntimeException(
                "Migración abortada: {$ambiguous} fila(s) Metas con habilitado=0 y etapa distinta de CERRADO. Resolver manualmente antes de continuar."
            );
        }

        DB::table('indicator_type_settings')
            ->whereIn('indicator_type_id', function ($query): void {
                $query->select('id')
                    ->from('indicator_types')
                    ->where('route', 'like', 'metas-%');
            })
            ->where('habilitado', false)
            ->where('etapa_vigente', 'CERRADO')
            ->update(['etapa_vigente' => 'NO_INICIADO']);
    }

    public function down(): void
    {
        DB::table('indicator_type_settings')
            ->whereIn('indicator_type_id', function ($query): void {
                $query->select('id')
                    ->from('indicator_types')
                    ->where('route', 'like', 'metas-%');
            })
            ->where('habilitado', false)
            ->where('etapa_vigente', 'NO_INICIADO')
            ->update(['etapa_vigente' => 'CERRADO']);
    }
};
