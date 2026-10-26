<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCenHieDependenciesRegionsTable extends Migration
{
    public function up()
    {
        Schema::create('cen_hie_dependencies_regions', function (Blueprint $table) {
            $table->bigInteger('cen_hie_dependency_id')->unsigned();
            $table->bigInteger('cen_region_id')->unsigned();
            $table->timestamps();
        });

        Schema::table('cen_hie_dependencies_regions', function (Blueprint $table) {
            $table->foreign('cen_hie_dependency_id')->references('id')->on('cen_hie_dependencies')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cen_region_id')->references('id')->on('cen_regions')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down()
    {
        Schema::drop('cen_hie_dependencies_regions');
    }
}
