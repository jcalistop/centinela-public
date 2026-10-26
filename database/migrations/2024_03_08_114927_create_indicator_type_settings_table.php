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
        Schema::create('indicator_type_settings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('year_id')->unsigned();
            $table->bigInteger('cen_hie_dependency_id')->unsigned();
            $table->bigInteger('indicator_type_id')->unsigned();
            $table->boolean('habilitado')->default(0);
            $table->string('panel_minsal')->nullable();
            $table->enum('etapa_vigente', ['CERRADO', 'AUTOEVALUACION', 'PRELIMINAR', 'APELACION', 'FINAL', 'ANUAL'])->default('CERRADO');
            $table->enum('tipo_evaluacion', ['ANUAL', 'SEMESTRAL', 'TRIMESTRAL', 'MENSUAL', 'NO DEFINIDO'])->default('NO DEFINIDO');
            $table->longText('texto_enriquecido')->nullable();
            $table->timestamps();

            $table->index(['year_id', 'cen_hie_dependency_id', 'indicator_type_id'], 'idx_its_compound');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_type_settings');
    }
};
