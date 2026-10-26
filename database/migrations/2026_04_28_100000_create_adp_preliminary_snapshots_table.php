<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_preliminary_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_agreement_period_id')->constrained('adp_agreement_periods')->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->decimal('weighted_global_percent', 8, 4)->nullable();
            $table->unsignedInteger('n_indicators')->nullable();
            $table->unsignedInteger('n_calculated')->nullable();
            $table->unsignedInteger('n_failed')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('cen_hie_dependency_id');
            $table->timestamps();

            $table->unique(
                ['adp_agreement_period_id', 'version'],
                'uq_adp_prelim_snap_prd_ver'
            );
            $table->index('cen_hie_dependency_id', 'adp_prelim_snap_hie_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_preliminary_snapshots');
    }
};
