<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cen_centers') && ! Schema::hasColumn('cen_centers', 'tarea_pendiente')) {
            Schema::table('cen_centers', function (Blueprint $table) {
                $table->boolean('tarea_pendiente')->default(false)->after('synced_at');
            });
        }

        if (Schema::hasTable('cen_center_notes') && ! Schema::hasColumn('cen_center_notes', 'note_kind')) {
            Schema::table('cen_center_notes', function (Blueprint $table) {
                $table->string('note_kind', 32)->default('user')->after('body');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cen_centers') && Schema::hasColumn('cen_centers', 'tarea_pendiente')) {
            Schema::table('cen_centers', function (Blueprint $table) {
                $table->dropColumn('tarea_pendiente');
            });
        }

        if (Schema::hasTable('cen_center_notes') && Schema::hasColumn('cen_center_notes', 'note_kind')) {
            Schema::table('cen_center_notes', function (Blueprint $table) {
                $table->dropColumn('note_kind');
            });
        }
    }
};
