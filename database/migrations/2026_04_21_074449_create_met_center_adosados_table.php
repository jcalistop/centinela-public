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
        Schema::create('met_center_adosados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cen_center_madre_id')->constrained('cen_centers')->restrictOnDelete();
            $table->foreignId('cen_center_adosado_id')->constrained('cen_centers')->restrictOnDelete();
            $table->foreignId('year_id')->constrained('years')->restrictOnDelete();
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['cen_center_adosado_id', 'year_id', 'cen_hie_dependency_id'],
                'uq_met_center_adosado'
            );
            $table->index(['cen_center_madre_id', 'year_id'], 'met_ctr_ados_madre_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('met_center_adosados');
    }
};
