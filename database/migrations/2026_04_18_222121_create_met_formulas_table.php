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
        Schema::create('met_formulas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('met_indicator_id');
            $table->json('expression');
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique('met_indicator_id', 'uq_met_formula_ind');
            $table->foreign('met_indicator_id', 'met_formulas_indicator_foreign')
                ->references('id')->on('met_indicators')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('met_formulas');
    }
};
