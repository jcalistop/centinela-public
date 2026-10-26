<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('met_indicators', function (Blueprint $table) {
            if (! Schema::hasColumn('met_indicators', 'prioridad')) {
                $table->string('prioridad', 32)->default('sin_prioridad')->after('estado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('met_indicators', function (Blueprint $table) {
            if (Schema::hasColumn('met_indicators', 'prioridad')) {
                $table->dropColumn('prioridad');
            }
        });
    }
};
