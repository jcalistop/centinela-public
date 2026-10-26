<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            $table->boolean('aplica')->default(true)->after('cen_center_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            $table->dropColumn('aplica');
        });
    }
};
