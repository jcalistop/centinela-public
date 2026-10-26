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
        Schema::create('cutoffs', function (Blueprint $table) {
            $table->id();
            $table->enum('clave', ['CORTE I', 'CORTE II', 'CORTE III', 'CORTE IV', 'CORTE UNICO', 'INDICADOR', 'REQUISITO', 'APELACION']);
            $table->enum('tipo_corte', ['ANUAL', 'SEMESTRAL', 'TRIMESTRAL', 'MENSUAL']);
            $table->json('meses');
            $table->text('nombre_corte')->nullable();
            $table->integer('orden')->default(0);
            $table->tinyInteger('habilitado')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cutoffs');
    }
};
