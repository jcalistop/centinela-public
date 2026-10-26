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
        Schema::create('platform_auth_sessions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('cen_hie_dependency_id')->nullable();

            $table->string('laravel_session_id', 191);

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 16)->nullable();

            $table->index(['user_id', 'started_at'], 'pas_user_started_idx');
            $table->index(['cen_hie_dependency_id', 'started_at'], 'pas_ss_started_idx');
            $table->index(['ended_at'], 'pas_ended_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_auth_sessions');
    }
};
