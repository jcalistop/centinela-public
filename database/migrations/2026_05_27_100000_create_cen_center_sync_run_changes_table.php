<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cen_center_sync_run_changes')) {
            return;
        }

        Schema::create('cen_center_sync_run_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cen_center_sync_run_id')
                ->constrained('cen_center_sync_runs')
                ->cascadeOnDelete();
            $table->string('establecimiento_codigo', 32)->nullable();
            $table->string('nombre_display', 255)->nullable();
            $table->string('action', 32);
            $table->string('detail', 64)->nullable();
            $table->timestamps();

            $table->index(['cen_center_sync_run_id', 'action']);
            $table->index('establecimiento_codigo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cen_center_sync_run_changes');
    }
};
