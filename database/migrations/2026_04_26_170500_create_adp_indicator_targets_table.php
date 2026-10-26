<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_indicator_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_indicator_id')
                ->constrained('adp_indicators')
                ->cascadeOnDelete();
            $table->decimal('weight_percent', 5, 2)->nullable();
            $table->decimal('target_value', 10, 4)->nullable();
            $table->enum('target_operador', ['>=', '>', '<=', '<', '='])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('adp_indicator_id', 'uq_adp_tgt_ind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_indicator_targets');
    }
};
