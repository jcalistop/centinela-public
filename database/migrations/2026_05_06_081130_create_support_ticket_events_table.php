<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32);
            $table->boolean('is_internal')->default(false);
            $table->text('comment')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at'], 'ste_ticket_created');
            $table->index(['type', 'created_at'], 'ste_type_created');
            $table->index(['is_internal', 'created_at'], 'ste_internal_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_events');
    }
};
