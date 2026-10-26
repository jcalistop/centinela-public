<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('met_establishment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cen_center_id')->constrained('cen_centers');
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies');
            $table->foreignId('year_id')->constrained('years');
            $table->string('periodo', 20);
            $table->decimal('resultado_global', 10, 4)->nullable();
            $table->unsignedSmallInteger('n_seleccionados')->nullable();
            $table->unsignedSmallInteger('n_calculados')->nullable();
            $table->timestamps();

            $table->unique(['cen_center_id', 'year_id', 'periodo'], 'uq_met_estab_result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('met_establishment_results');
    }
};
