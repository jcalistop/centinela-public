<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_monthly_data', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_indicator_id')->constrained('adp_indicators')->cascadeOnDelete();
            $table->foreignId('adp_agreement_period_id')->constrained('adp_agreement_periods')->cascadeOnDelete();
            $table->date('month_date');
            $table->decimal('valor', 15, 4)->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('cen_center_id')->constrained('cen_centers');
            $table->unsignedBigInteger('cen_hie_dependency_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['adp_indicator_id', 'adp_agreement_period_id', 'month_date'],
                'uq_adp_mo_ind_prd_mo'
            );
            $table->index('cen_hie_dependency_id', 'adp_mo_hie_idx');
            $table->index('adp_agreement_period_id', 'adp_mo_prd_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_monthly_data');
    }
};
