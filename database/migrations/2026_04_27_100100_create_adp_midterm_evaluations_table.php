<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_midterm_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_agreement_period_id')->constrained('adp_agreement_periods')->cascadeOnDelete();
            $table->date('evaluated_on');
            $table->string('status', 24)->default('draft');
            $table->text('observaciones')->nullable();
            $table->json('results_snapshot')->nullable();
            $table->unsignedBigInteger('cen_hie_dependency_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('adp_agreement_period_id', 'uq_adp_midterm_prd');
            $table->index('cen_hie_dependency_id', 'adp_mid_hie_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_midterm_evaluations');
    }
};
