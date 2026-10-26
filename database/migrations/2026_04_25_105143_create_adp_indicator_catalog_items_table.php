<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_indicator_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cen_hie_dependency_id');
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
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cen_hie_dependency_id', 'clave'], 'uq_adp_cat_hie_clave');
            $table->index('cen_hie_dependency_id', 'adp_cat_hie_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_indicator_catalog_items');
    }
};
