<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_indicator_catalog_formulas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('adp_indicator_catalog_item_id');
            $table->json('expression');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('adp_indicator_catalog_item_id', 'uq_adp_cat_formula_item');
            $table->foreign('adp_indicator_catalog_item_id', 'fk_adp_cat_formula_item')
                ->references('id')
                ->on('adp_indicator_catalog_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_indicator_catalog_formulas');
    }
};
