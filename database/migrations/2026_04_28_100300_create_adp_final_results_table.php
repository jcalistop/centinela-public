<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_final_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_agreement_period_id')->constrained('adp_agreement_periods')->cascadeOnDelete();
            $table->foreignId('adp_preliminary_snapshot_id')->nullable()->constrained('adp_preliminary_snapshots')->nullOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->decimal('weighted_global_percent', 8, 4)->nullable();
            $table->boolean('published_without_preliminary')->default(false);
            $table->text('published_without_preliminary_reason')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('cen_hie_dependency_id');
            $table->timestamps();

            $table->unique(
                ['adp_agreement_period_id', 'version'],
                'uq_adp_final_res_prd_ver'
            );
            $table->index('cen_hie_dependency_id', 'adp_final_res_hie_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_final_results');
    }
};
