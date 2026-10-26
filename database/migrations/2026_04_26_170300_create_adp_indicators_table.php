<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_indicators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_objective_id')
                ->constrained('adp_objectives')
                ->cascadeOnDelete();
            $table->string('clave');
            $table->string('nombre');
            $table->text('objetivo')->nullable();
            $table->text('meta_anual_descripcion')->nullable();
            $table->decimal('meta_valor', 10, 4)->nullable();
            $table->enum('meta_operador', ['>=', '>', '<=', '<', '='])->nullable();
            $table->json('meta_reglas')->nullable();
            $table->decimal('umbral_inferior', 5, 2)->nullable();
            $table->decimal('umbral_superior', 5, 2)->nullable();
            $table->text('formula_descripcion')->nullable();
            $table->text('fuente_numerador')->nullable();
            $table->text('fuente_denominador')->nullable();
            $table->enum('periodicidad', ['TRIMESTRAL', 'SEMESTRAL', 'ANUAL'])->default('ANUAL');
            $table->text('nivel_aplicacion')->nullable();
            $table->string('unidad_medida')->nullable();
            $table->unsignedTinyInteger('decimales')->default(2);
            $table->enum('decimales_modo', ['truncar', 'redondear'])->nullable();
            $table->string('calculation_method', 32)->default('percent');
            $table->unsignedSmallInteger('orden')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index('adp_objective_id', 'adp_ind_obj_idx');
            $table->unique(['adp_objective_id', 'clave'], 'adp_ind_obj_clave_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_indicators');
    }
};
