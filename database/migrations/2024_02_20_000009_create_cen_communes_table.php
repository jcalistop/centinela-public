<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCenCommunesTable extends Migration
{
    public function up()
    {
        Schema::create('cen_communes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('nombre');
            $table->bigInteger('cen_province_id')->unsigned();
            $table->timestamps();
        });
        Schema::table('cen_communes', function (Blueprint $table) {
            $table->foreign('cen_province_id')->references('id')->on('cen_provinces')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down()
    {
        Schema::drop('cen_communes');
    }
}
