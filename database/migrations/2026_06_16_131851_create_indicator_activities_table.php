<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_activities', function (Blueprint $table) {
            $table->id();
            $table->string('indicator_type', 20);
            $table->unsignedBigInteger('indicator_id');
            $table->string('action', 100);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['indicator_type', 'indicator_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_activities');
    }
};
