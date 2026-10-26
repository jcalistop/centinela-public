<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messaging_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('audience', 64);
            $table->unsignedBigInteger('cen_hie_dependency_id')->nullable();
            $table->string('subject');
            $table->longText('body_html');
            $table->longText('body_text');
            $table->string('status', 32)->default('queued');
            $table->unsignedInteger('recipients_total')->default(0);
            $table->unsignedInteger('recipients_sent')->default(0);
            $table->unsignedInteger('recipients_failed')->default(0);
            $table->timestamps();

            $table->index(['sender_id', 'status']);
            $table->foreign('cen_hie_dependency_id')
                ->references('id')
                ->on('cen_hie_dependencies')
                ->nullOnDelete();
        });

        Schema::create('messaging_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('messaging_campaign_id')->constrained('messaging_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('status', 32)->default('queued');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['messaging_campaign_id', 'user_id'], 'messaging_campaign_user_unique');
            $table->index(['messaging_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_campaign_recipients');
        Schema::dropIfExists('messaging_campaigns');
    }
};
