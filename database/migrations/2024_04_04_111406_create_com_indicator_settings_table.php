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
        Schema::create('com_indicator_settings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cen_hie_dependency_id');
            $table->bigInteger('com_indicator_id');
            $table->date('fecha_apertura')->nullable();
            $table->date('fecha_cierre')->nullable();
            $table->tinyInteger('mantener_abierto')->nullable();
            $table->json('pestanas_editables')->nullable();
            $table->timestamps();

            $table->unique(['cen_hie_dependency_id', 'com_indicator_id'], 'cen_hie_dependency_id_com_indicator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('com_indicator_settings');
    }
};
