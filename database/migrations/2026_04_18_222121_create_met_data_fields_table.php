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
        Schema::create('met_data_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('met_indicator_id');
            $table->string('label');
            $table->string('variable_key', 2);
            $table->enum('role', ['numerador', 'denominador']);
            $table->enum('input_type', ['mensual', 'semestral', 'linea_base'])->default('mensual');
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['met_indicator_id', 'variable_key'], 'uq_met_field_key');
            $table->foreign('met_indicator_id', 'met_data_fields_indicator_foreign')
                ->references('id')->on('met_indicators')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('met_data_fields');
    }
};
