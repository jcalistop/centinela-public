<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('public_dashboard_visit_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('total')->default(0);
        });

        DB::table('public_dashboard_visit_counters')->insert([
            'total' => 0,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_dashboard_visit_counters');
    }
};
