<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('met_indicators', function (Blueprint $table) {
            $table->enum('decimales_modo', ['truncar', 'redondear'])
                ->default('redondear')
                ->after('decimales');
        });
    }

    public function down(): void
    {
        Schema::table('met_indicators', function (Blueprint $table) {
            $table->dropColumn('decimales_modo');
        });
    }
};
