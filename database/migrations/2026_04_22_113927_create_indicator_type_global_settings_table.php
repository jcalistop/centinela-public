<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_type_global_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('indicator_type_id')->constrained('indicator_types')->restrictOnDelete();
            $table->foreignId('year_id')->constrained('years')->restrictOnDelete();
            $table->tinyInteger('min_indicadores')->unsigned()->default(4)
                ->comment('Mínimo de indicadores seleccionados por establecimiento');
            $table->decimal('min_ponderacion', 5, 2)->default(0.00)
                ->comment('Ponderación mínima permitida por indicador (inclusive)');
            $table->decimal('max_ponderacion', 5, 2)->default(100.00)
                ->comment('Ponderación máxima permitida por indicador (inclusive)');
            $table->timestamps();

            $table->unique(['indicator_type_id', 'year_id'], 'uq_itgs_type_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_type_global_settings');
    }
};
