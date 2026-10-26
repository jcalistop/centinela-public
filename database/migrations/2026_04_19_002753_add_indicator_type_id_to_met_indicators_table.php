<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Agregar columna nullable inicialmente
        Schema::table('met_indicators', function (Blueprint $table) {
            $table->unsignedBigInteger('indicator_type_id')->nullable()->after('id');
        });

        // 2. Backfill: asignar indicador tipo 'metas-18834' a todos los registros existentes
        $indicatorTypeId = DB::table('indicator_types')
            ->where('clave_indicador', 'metas-18834')
            ->value('id');

        if ($indicatorTypeId) {
            DB::table('met_indicators')->update(['indicator_type_id' => $indicatorTypeId]);
        }

        // 3. Cambiar a NOT NULL y agregar FK
        Schema::table('met_indicators', function (Blueprint $table) {
            $table->unsignedBigInteger('indicator_type_id')->nullable(false)->change();
            $table->foreign('indicator_type_id')
                ->references('id')
                ->on('indicator_types')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('met_indicators', function (Blueprint $table) {
            $table->dropForeign(['indicator_type_id']);
            $table->dropColumn('indicator_type_id');
        });
    }
};
