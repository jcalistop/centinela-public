<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_indicator_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_indicator_id')->constrained('adp_indicators')->cascadeOnDelete();
            $table->foreignId('adp_agreement_period_id')->constrained('adp_agreement_periods')->cascadeOnDelete();
            $table->decimal('raw_result', 15, 6)->nullable();
            $table->decimal('compliance_percent', 8, 4)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('cen_hie_dependency_id');
            $table->dateTime('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['adp_indicator_id', 'adp_agreement_period_id'],
                'uq_adp_ind_res_ind_prd'
            );
            $table->index('cen_hie_dependency_id', 'adp_ind_res_hie_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_indicator_results');
    }
};
