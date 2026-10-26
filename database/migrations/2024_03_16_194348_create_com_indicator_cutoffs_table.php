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
        Schema::create('com_indicator_cutoffs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('com_indicator_id');
            $table->bigInteger('cutoff_id');
            $table->decimal('ponderacion_corte', 6, 3);
            $table->decimal('promedio_pais', 5, 2)->nullable();
            $table->tinyInteger('evaluacion_local')->nullable()->default(0);
            $table->timestamps();

            $table->index('cutoff_id', 'idx_com_ind_cutoffs_cutoff_id');
            $table->index('com_indicator_id', 'idx_com_ind_cutoffs_indicator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('com_indicator_cutoffs');
    }
};
