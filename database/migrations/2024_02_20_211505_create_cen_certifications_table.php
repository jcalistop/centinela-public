<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCenCertificationsTable extends Migration
{
    public function up()
    {
        Schema::create('cen_certifications', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('cen_certifications');
    }
}
