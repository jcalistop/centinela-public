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
        Schema::create('com_verifiable_documents', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('com_action_id');
            $table->bigInteger('cen_hie_dependency_id');
            $table->text('archivos')->nullable();
            $table->longtext('descripcion');
            $table->enum('verifiable_type', ['EVALUACION', 'APELACION'])->default('EVALUACION');
            $table->timestamps();

            $table->index('com_action_id', 'idx_com_verif_docs_action_id');
            $table->index('cen_hie_dependency_id', 'idx_com_verif_docs_dep_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('com_verifiable_documents');
    }
};
