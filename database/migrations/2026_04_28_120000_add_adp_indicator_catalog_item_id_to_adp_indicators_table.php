<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adp_indicators', function (Blueprint $table): void {
            $table->unsignedBigInteger('adp_indicator_catalog_item_id')->nullable()->after('adp_objective_id');
            $table->foreign('adp_indicator_catalog_item_id', 'fk_adp_ind_cat_item')
                ->references('id')
                ->on('adp_indicator_catalog_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('adp_indicators', function (Blueprint $table): void {
            $table->dropForeign('fk_adp_ind_cat_item');
            $table->dropColumn('adp_indicator_catalog_item_id');
        });
    }
};
