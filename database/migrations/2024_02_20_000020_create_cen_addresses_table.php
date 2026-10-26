<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCenAddressesTable extends Migration
{
    public function up()
    {
        Schema::create('cen_addresses', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cen_center_id')->unsigned();
            $table->bigInteger('cen_commune_id')->unsigned();
            $table->enum('via', ['AVENIDA', 'CALLE', 'CAMINO', 'CARRETERA', 'OTRO', 'PASAJE', 'SECTOR', 'PENDIENTE']);
            $table->string('numero')->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->decimal('latitud', 11, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->timestamps();
        });
        Schema::table('cen_addresses', function (Blueprint $table) {
            $table->foreign('cen_center_id')->references('id')->on('cen_centers')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cen_commune_id')->references('id')->on('cen_communes')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down()
    {
        Schema::drop('cen_addresses');
    }
}
