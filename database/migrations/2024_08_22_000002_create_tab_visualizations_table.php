<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTabVisualizationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tab_visualizations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('menu_visualization_id')->unsigned()->index();
            $table->text('nombre_tab');
            $table->tinyInteger('habilitado')->nullable();
            $table->longText('texto_enriquecido')->nullable();
            $table->text('codigo_incrustado')->nullable();
            $table->text('data_json')->nullable();
            $table->text('tipo_grafico')->nullable();
            $table->tinyInteger('muestra_tabla')->default(0);
            $table->integer('orden')->default(0);
            $table->text('nombre_orden')->nullable();
            $table->timestamp('created_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tab_visualizations', function (Blueprint $table) {
            $table->dropForeign('tab_visualizations_menu_visualization_id_foreign');
        });

        Schema::dropIfExists('tab_visualizations');
    }
}
