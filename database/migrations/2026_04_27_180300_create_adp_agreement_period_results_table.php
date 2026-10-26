<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_agreement_period_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_agreement_period_id')->constrained('adp_agreement_periods')->cascadeOnDelete();
            $table->decimal('weighted_global_percent', 8, 4)->nullable();
            $table->unsignedSmallInteger('n_indicators')->default(0);
            $table->unsignedSmallInteger('n_calculated')->default(0);
            $table->unsignedSmallInteger('n_failed')->default(0);
            $table->json('details_json')->nullable();
            $table->unsignedBigInteger('cen_hie_dependency_id');
            $table->dateTime('calculated_at')->nullable();
            $table->timestamps();

            $table->unique('adp_agreement_period_id', 'uq_adp_prd_res_prd');
            $table->index('cen_hie_dependency_id', 'adp_prd_res_hie_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_agreement_period_results');
    }
};
