<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('met_indicators', function (Blueprint $table) {
            $table->dropColumn('periodicidad');
        });
    }

    public function down(): void
    {
        Schema::table('met_indicators', function (Blueprint $table) {
            $table->enum('periodicidad', ['MENSUAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL'])
                ->default('ANUAL')
                ->after('fuente_denominador');
        });
    }
};
