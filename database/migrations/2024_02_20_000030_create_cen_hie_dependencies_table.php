<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCenHieDependenciesTable extends Migration
{
    public function up()
    {
        Schema::create('cen_hie_dependencies', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('nombre');
            $table->enum('tipo_dependencia', ['SERVICIO', 'SEREMI', 'OTRO']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('cen_hie_dependencies');
    }
}
