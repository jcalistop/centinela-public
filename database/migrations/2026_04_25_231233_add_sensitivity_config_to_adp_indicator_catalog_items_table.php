<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adp_indicator_catalog_items', function (Blueprint $table): void {
            $table->json('sensitivity_config')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('adp_indicator_catalog_items', function (Blueprint $table): void {
            $table->dropColumn('sensitivity_config');
        });
    }
};
