<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCenProviderTypesTable extends Migration
{
    public function up()
    {
        Schema::create('cen_provider_types', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('cen_provider_types');
    }
}
