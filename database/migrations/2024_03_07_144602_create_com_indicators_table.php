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
        Schema::create('com_indicators', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('com_commitment_id')->unsigned();
            $table->string('clave');
            $table->string('nombre');
            $table->decimal('ponderacion_indicador', 5, 4);
            $table->text('descripcion')->nullable();
            $table->timestamps();

            $table->foreign('com_commitment_id')->references('id')->on('com_commitments')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('com_indicators');
    }
};
