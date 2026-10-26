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
        Schema::create('com_evaluations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('com_action_id');
            $table->bigInteger('cen_hie_dependency_id');
            $table->decimal('numerador', 15, 2);
            $table->decimal('denominador', 15, 2);
            $table->decimal('resultado', 6, 2);
            $table->string('meta')->nullable();
            $table->integer('cumplimiento');
            $table->longText('observaciones')->nullable();
            $table->enum('tipo_evaluacion', ['AUTOEVALUACION', 'PRELIMINAR', 'FINAL']);
            $table->timestamps();

            $table->index('com_action_id', 'idx_com_eval_action_id');
            $table->index('cen_hie_dependency_id', 'idx_com_eval_dep_id');
            $table->index(['com_action_id', 'cen_hie_dependency_id', 'tipo_evaluacion'], 'idx_com_eval_compound');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('com_evaluations');
    }
};
