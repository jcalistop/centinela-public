<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('met_indicator_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('met_indicator_id')->constrained('met_indicators')->cascadeOnDelete();
            $table->foreignId('year_id')->constrained('years');
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies');
            $table->boolean('seleccionado')->default(false);
            $table->decimal('ponderacion', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['met_indicator_id', 'year_id', 'cen_hie_dependency_id'], 'uq_met_ind_config');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('met_indicator_configurations');
    }
};
