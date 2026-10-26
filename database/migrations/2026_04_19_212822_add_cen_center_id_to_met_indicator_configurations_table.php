<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar columna si todavía no existe (la migración puede haber fallado a medias)
        if (! Schema::hasColumn('met_indicator_configurations', 'cen_center_id')) {
            Schema::table('met_indicator_configurations', function (Blueprint $table) {
                $table->foreignId('cen_center_id')
                    ->nullable()
                    ->after('year_id')
                    ->constrained('cen_centers')
                    ->restrictOnDelete();
            });
        }

        // Agregar índice regular en met_indicator_id para que MySQL tenga backing index
        // alternativo antes de eliminar el unique que cubre esa columna como prefijo
        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            $table->index('met_indicator_id', 'idx_met_ind_config_indicator_id');
        });

        // Eliminar unique constraint anterior (por cen_hie_dependency_id)
        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            $table->dropUnique('uq_met_ind_config');
        });

        // Agregar nuevo unique constraint (por cen_center_id)
        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            $table->unique(['met_indicator_id', 'year_id', 'cen_center_id'], 'uq_met_ind_config');
        });

        // Eliminar el índice temporal (el nuevo unique ya cubre met_indicator_id como prefijo)
        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            $table->dropIndex('idx_met_ind_config_indicator_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('met_indicator_configurations', 'cen_center_id')) {
            return;
        }

        // Mismo patrón que up(): índice alternativo antes de dropear el unique (FK met_indicator_id).
        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            $table->index('met_indicator_id', 'idx_met_ind_config_indicator_id');
        });

        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            $table->dropUnique('uq_met_ind_config');
        });

        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cen_center_id');
        });

        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            $table->unique(['met_indicator_id', 'year_id', 'cen_hie_dependency_id'], 'uq_met_ind_config');
        });

        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            $table->dropIndex('idx_met_ind_config_indicator_id');
        });
    }
};
