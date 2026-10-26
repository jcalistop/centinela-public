<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMenuVisualizationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('menu_visualizations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cen_hie_dependency_id')->unsigned();
            $table->bigInteger('year_id')->unsigned();
            $table->text('menu_superior')->nullable();
            $table->text('nombre_menu');
            $table->text('titulo');
            $table->longText('texto_enriquecido')->nullable();
            $table->text('icon_class')->nullable();
            $table->text('route')->nullable();
            $table->tinyInteger('estado');
            $table->tinyInteger('orden');
            $table->text('nombre_orden')->nullable();
            $table->timestamp('created_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('updated_at')->nullable();

            $table->index(['cen_hie_dependency_id', 'year_id'], 'idx_menu_vis_dep_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_visualizations', function (Blueprint $table) {
            $table->dropForeign('menu_visualizations_cen_hie_dependency_id_foreign');
            $table->dropForeign('menu_visualizations_year_id_foreign');
        });

        Schema::dropIfExists('menu_visualizations');
    }
}
