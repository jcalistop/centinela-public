<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('met_data_fields', function (Blueprint $table) {
            $table->enum('aggregation_type', ['sumatoria', 'ultimo_dato'])
                ->default('sumatoria')
                ->nullable()
                ->after('input_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('met_data_fields', function (Blueprint $table) {
            $table->dropColumn('aggregation_type');
        });
    }
};
