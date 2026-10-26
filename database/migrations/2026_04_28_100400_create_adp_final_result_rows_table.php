<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_final_result_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_final_result_id')->constrained('adp_final_results')->cascadeOnDelete();
            $table->foreignId('adp_indicator_id')->constrained('adp_indicators')->cascadeOnDelete();
            $table->decimal('preliminary_compliance_percent', 8, 4)->nullable();
            $table->decimal('final_compliance_percent', 8, 4)->nullable();
            $table->decimal('raw_result', 15, 6)->nullable();
            $table->boolean('is_overridden')->default(false);
            $table->timestamps();

            $table->unique(
                ['adp_final_result_id', 'adp_indicator_id'],
                'uq_adp_final_row_res_ind'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_final_result_rows');
    }
};
