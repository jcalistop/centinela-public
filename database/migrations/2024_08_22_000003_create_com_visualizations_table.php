<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateComVisualizationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('com_visualizations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('com_indicator_cutoff_id')->unsigned();
            $table->bigInteger('cen_hie_dependency_id')->unsigned();
            $table->tinyInteger('habilitado')->default(0);
            $table->text('titulo')->nullable();
            $table->longText('texto_enriquecido')->nullable();
            $table->text('codigo_incrustado')->nullable();
            $table->text('data')->nullable();
            $table->text('tipo_grafico')->nullable();
            $table->tinyInteger('muestra_tabla')->default(0);
            $table->timestamps();

            $table->index('com_indicator_cutoff_id', 'idx_com_vis_cutoff_id');
            $table->index('cen_hie_dependency_id', 'idx_com_vis_dep_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('com_visualizations', function (Blueprint $table) {
            $table->dropForeign('com_visualizations_com_indicator_cutoff_id_foreign');
            $table->dropForeign('com_visualizations_cen_hie_dependency_id_foreign');
        });

        Schema::dropIfExists('com_visualizations');
    }
}
