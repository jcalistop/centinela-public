<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voyager multi-file JSON en `documents.file` supera VARCHAR(255).
     *
     * Rollback a string truncaría filas ya largas: no ejecutar `down` en
     * entornos con adjuntos múltiples persistidos.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->text('file')->change();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('file')->change();
        });
    }
};
