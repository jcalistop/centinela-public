<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('met_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->string('nombre');
            $table->text('objetivo')->nullable();
            $table->text('meta_anual_descripcion')->nullable();
            $table->decimal('meta_valor', 10, 4)->nullable();
            $table->enum('meta_operador', ['>=', '>', '<=', '<', '='])->nullable();
            $table->json('meta_reglas')->nullable();
            $table->text('formula_descripcion')->nullable();
            $table->text('fuente_numerador')->nullable();
            $table->text('fuente_denominador')->nullable();
            $table->enum('periodicidad', ['MENSUAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL'])->default('ANUAL');
            $table->text('nivel_aplicacion')->nullable();
            $table->string('unidad_medida')->nullable();
            $table->unsignedTinyInteger('decimales')->default(2);
            $table->foreignId('year_id')->constrained('years');
            $table->unsignedSmallInteger('orden')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('met_indicators');
    }
};
