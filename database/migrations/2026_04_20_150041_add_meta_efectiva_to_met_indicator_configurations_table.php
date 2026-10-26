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
            $table->decimal('meta_efectiva', 10, 4)->nullable()->after('ponderacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('met_indicator_configurations', function (Blueprint $table) {
            $table->dropColumn('meta_efectiva');
        });
    }
};
