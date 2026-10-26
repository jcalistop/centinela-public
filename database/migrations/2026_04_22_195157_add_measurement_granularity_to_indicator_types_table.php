<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicator_types', function (Blueprint $table) {
            $table->string('measurement_granularity', 32)
                ->default('establishment')
                ->after('orden');
        });
    }

    public function down(): void
    {
        Schema::table('indicator_types', function (Blueprint $table) {
            $table->dropColumn('measurement_granularity');
        });
    }
};
