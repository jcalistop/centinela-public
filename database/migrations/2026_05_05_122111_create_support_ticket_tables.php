<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 255);
            $table->text('body');
            $table->string('status', 32)->default('open');
            $table->string('area', 32);
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('indicator_type_id')->nullable()->constrained('indicator_types')->nullOnDelete();
            $table->foreignId('year_id')->nullable()->constrained('years')->nullOnDelete();
            $table->unsignedBigInteger('github_issue_number')->nullable();
            $table->string('github_issue_url', 512)->nullable();
            $table->timestamp('github_linked_at')->nullable();
            $table->foreignId('github_linked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['cen_hie_dependency_id', 'status', 'created_at'], 'st_cen_dep_stat_created');
            $table->index(['assigned_to_user_id', 'status', 'created_at'], 'st_assign_stat_created');
            $table->index(['indicator_type_id', 'created_at'], 'st_ind_type_created');
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at'], 'stm_ticket_created');
        });

        Schema::create('support_ticket_receivers', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16);
            $table->foreignId('cen_hie_dependency_id')->nullable()->constrained('cen_hie_dependencies')->nullOnDelete();
            $table->string('area', 32);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['scope', 'area', 'enabled'], 'str_scope_area_en');
            $table->index(['cen_hie_dependency_id', 'area', 'enabled'], 'str_cen_area_en');
        });

        Schema::create('support_ticket_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_read_at');
            $table->timestamps();

            $table->unique(['support_ticket_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_reads');
        Schema::dropIfExists('support_ticket_receivers');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
