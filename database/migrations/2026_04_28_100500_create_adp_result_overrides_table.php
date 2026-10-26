<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_result_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_final_result_id')->constrained('adp_final_results')->cascadeOnDelete();
            $table->foreignId('adp_indicator_id')->constrained('adp_indicators')->cascadeOnDelete();
            $table->string('field_name', 64)->default('final_compliance_percent');
            $table->decimal('previous_value', 15, 6)->nullable();
            $table->decimal('new_value', 15, 6)->nullable();
            $table->text('reason');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['adp_final_result_id', 'adp_indicator_id'], 'adp_overrides_res_ind_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_result_overrides');
    }
};
