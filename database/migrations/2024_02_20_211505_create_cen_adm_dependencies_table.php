<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCenAdmDependenciesTable extends Migration
{
    public function up()
    {
        Schema::create('cen_adm_dependencies', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('cen_adm_dependencies');
    }
}
