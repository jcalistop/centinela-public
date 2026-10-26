<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        if (! Schema::hasTable('indicator_type_settings')) {
            return;
        }

        Schema::table('indicator_type_settings', function (Blueprint $table): void {
            $table->string('etapa_vigente', 20)->default('CERRADO')->change();
            $table->string('tipo_evaluacion', 20)->default('NO DEFINIDO')->change();
        });
    }

    public function down(): void
    {
        // No reversible automáticamente en SQLite.
    }
};
