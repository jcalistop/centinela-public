<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cen_center_sync_run_issues')) {
            return;
        }

        Schema::table('cen_center_sync_run_issues', function (Blueprint $table): void {
            if (! Schema::hasColumn('cen_center_sync_run_issues', 'promoted_at')) {
                $table->timestamp('promoted_at')->nullable()->after('context');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cen_center_sync_run_issues')) {
            return;
        }

        Schema::table('cen_center_sync_run_issues', function (Blueprint $table): void {
            if (Schema::hasColumn('cen_center_sync_run_issues', 'promoted_at')) {
                $table->dropColumn('promoted_at');
            }
        });
    }
};
