<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('met_indicator_appeals', function (Blueprint $table) {
            $table->dateTime('submitted_at')->nullable()->after('created_by');
        });

        DB::table('met_indicator_appeals')
            ->whereNull('submitted_at')
            ->update(['submitted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('met_indicator_appeals', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};
