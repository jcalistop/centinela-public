<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_indicator_appeals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_agreement_period_id')->constrained('adp_agreement_periods')->cascadeOnDelete();
            $table->foreignId('adp_preliminary_snapshot_id')->constrained('adp_preliminary_snapshots')->cascadeOnDelete();
            $table->foreignId('adp_indicator_id')->constrained('adp_indicators')->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            $table->text('motivo');
            $table->text('resolucion')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->unsignedBigInteger('cen_hie_dependency_id');
            $table->timestamps();

            $table->index(
                ['adp_agreement_period_id', 'adp_indicator_id', 'status'],
                'adp_appeals_prd_ind_status_idx'
            );
            $table->index('cen_hie_dependency_id', 'adp_appeals_hie_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_indicator_appeals');
    }
};
