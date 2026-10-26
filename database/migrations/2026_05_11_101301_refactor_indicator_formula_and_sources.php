<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('met_data_fields', 'fuente')) {
            Schema::table('met_data_fields', function (Blueprint $table) {
                $table->string('fuente', 20)->nullable()->after('aggregation_type');
            });
        }

        $indicatorColumnsToDrop = array_values(array_filter([
            Schema::hasColumn('met_indicators', 'formula_descripcion') ? 'formula_descripcion' : null,
            Schema::hasColumn('met_indicators', 'fuente_numerador') ? 'fuente_numerador' : null,
            Schema::hasColumn('met_indicators', 'fuente_denominador') ? 'fuente_denominador' : null,
        ]));

        if ($indicatorColumnsToDrop !== []) {
            Schema::table('met_indicators', function (Blueprint $table) use ($indicatorColumnsToDrop) {
                $table->dropColumn($indicatorColumnsToDrop);
            });
        }
    }

    public function down(): void
    {
        Schema::table('met_indicators', function (Blueprint $table) {
            if (! Schema::hasColumn('met_indicators', 'formula_descripcion')) {
                $table->text('formula_descripcion')->nullable();
            }

            if (! Schema::hasColumn('met_indicators', 'fuente_numerador')) {
                $table->text('fuente_numerador')->nullable();
            }

            if (! Schema::hasColumn('met_indicators', 'fuente_denominador')) {
                $table->text('fuente_denominador')->nullable();
            }
        });

        if (Schema::hasColumn('met_data_fields', 'fuente')) {
            Schema::table('met_data_fields', function (Blueprint $table) {
                $table->dropColumn('fuente');
            });
        }
    }
};
