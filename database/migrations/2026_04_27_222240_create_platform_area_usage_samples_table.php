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
        Schema::create('platform_area_usage_samples', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cen_hie_dependency_id')->nullable();

            $table->string('area', 32);
            $table->string('laravel_session_id', 191);

            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at', 'paus_created_at_idx');
            $table->index(['cen_hie_dependency_id', 'created_at'], 'paus_ss_created_idx');
            $table->index(['area', 'created_at'], 'paus_area_created_idx');
            $table->index(['user_id', 'created_at'], 'paus_user_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_area_usage_samples');
    }
};
