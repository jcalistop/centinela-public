<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_request_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 16);
            $table->string('path', 512);
            $table->string('query_hash', 64)->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->string('ip', 45)->nullable();
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamps();

            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_request_logs');
    }
};
