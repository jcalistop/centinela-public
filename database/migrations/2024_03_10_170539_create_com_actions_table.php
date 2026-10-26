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
        Schema::create('com_actions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('com_indicator_cutoff_id');
            $table->text('numero');
            $table->longText('meta_texto')->nullable();
            $table->enum('tipo_medio', ['LOCAL', 'CENTRALIZADO', 'MIXTO'])->nullable();
            $table->longText('medio_verificacion')->nullable();
            $table->longText('formula')->nullable();
            $table->text('fuente')->nullable();
            $table->text('observaciones')->nullable();
            $table->decimal('ponderacion_accion', 5, 2)->nullable();
            $table->timestamps();

            $table->index('com_indicator_cutoff_id', 'idx_com_actions_cutoff_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('com_actions');
    }
};
