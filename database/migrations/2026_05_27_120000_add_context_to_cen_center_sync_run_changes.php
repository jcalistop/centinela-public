<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cen_center_sync_run_changes')) {
            return;
        }

        Schema::table('cen_center_sync_run_changes', function (Blueprint $table): void {
            if (! Schema::hasColumn('cen_center_sync_run_changes', 'context')) {
                $table->json('context')->nullable()->after('detail');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cen_center_sync_run_changes')) {
            return;
        }

        Schema::table('cen_center_sync_run_changes', function (Blueprint $table): void {
            if (Schema::hasColumn('cen_center_sync_run_changes', 'context')) {
                $table->dropColumn('context');
            }
        });
    }
};
