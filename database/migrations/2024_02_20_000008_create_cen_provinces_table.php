<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCenProvincesTable extends Migration
{
    public function up()
    {
        Schema::create('cen_provinces', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->bigInteger('cen_region_id')->unsigned();
            $table->timestamps();
        });
        Schema::table('cen_provinces', function (Blueprint $table) {
            $table->foreign('cen_region_id')->references('id')->on('cen_regions')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down()
    {
        Schema::drop('cen_provinces');
    }
}
