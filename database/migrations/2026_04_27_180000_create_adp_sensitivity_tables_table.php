<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_sensitivity_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_indicator_id')->constrained('adp_indicators')->cascadeOnDelete();
            $table->string('nombre')->nullable();
            $table->timestamps();

            $table->unique('adp_indicator_id', 'uq_adp_sens_ind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_sensitivity_tables');
    }
};
