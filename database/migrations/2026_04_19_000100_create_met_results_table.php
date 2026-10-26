<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('met_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('met_indicator_id')->constrained('met_indicators');
            $table->foreignId('cen_center_id')->constrained('cen_centers');
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies');
            $table->foreignId('year_id')->constrained('years');
            $table->string('periodo', 20);
            $table->decimal('numerador', 15, 4)->nullable();
            $table->decimal('denominador', 15, 4)->nullable();
            $table->decimal('resultado', 15, 4)->nullable();
            $table->boolean('cumple_meta')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamps();

            $table->unique(['met_indicator_id', 'cen_center_id', 'year_id', 'periodo'], 'uq_met_result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('met_results');
    }
};
