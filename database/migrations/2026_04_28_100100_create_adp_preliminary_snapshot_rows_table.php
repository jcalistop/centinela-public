<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_preliminary_snapshot_rows', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('adp_preliminary_snapshot_id');
            $table->unsignedBigInteger('adp_indicator_id');
            $table->decimal('raw_result', 15, 6)->nullable();
            $table->decimal('compliance_percent', 8, 4)->nullable();
            $table->decimal('weight_percent', 8, 4)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['adp_preliminary_snapshot_id', 'adp_indicator_id'],
                'uq_adp_prelim_snap_row_snap_ind'
            );

            $table->foreign('adp_preliminary_snapshot_id', 'fk_adp_prelim_row_snap')
                ->references('id')->on('adp_preliminary_snapshots')->cascadeOnDelete();
            $table->foreign('adp_indicator_id', 'fk_adp_prelim_row_ind')
                ->references('id')->on('adp_indicators')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_preliminary_snapshot_rows');
    }
};
