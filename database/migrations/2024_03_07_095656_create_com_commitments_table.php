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
        Schema::create('com_commitments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('indicator_type_id')->unsigned();
            $table->string('clave');
            $table->text('nombre');
            $table->text('descripcion')->nullable();
            $table->integer('orden')->default(0);
            $table->bigInteger('year_id')->unsigned();
            $table->timestamps();

            $table->index(['year_id', 'indicator_type_id'], 'idx_com_commitments_year_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('com_commitments');
    }
};
