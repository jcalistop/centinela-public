<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('descripcion');
            $table->string('autor');
            $table->string('file');
            $table->bigInteger('year_id')->unsigned();
            $table->bigInteger('indicator_type_id')->unsigned();
            $table->bigInteger('cen_hie_dependency_id')->unsigned();
            $table->bigInteger('user_id')->unsigned();
            $table->timestamps();

            $table->index('user_id', 'idx_documents_user_id');
            $table->index(['year_id', 'cen_hie_dependency_id'], 'idx_documents_year_dep');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
