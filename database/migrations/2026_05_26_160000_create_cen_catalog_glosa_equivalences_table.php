<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cen_catalog_glosa_equivalences')) {
            return;
        }

        Schema::create('cen_catalog_glosa_equivalences', function (Blueprint $table) {
            $table->id();
            $table->string('field_key', 64);
            $table->string('source_glosa', 512);
            $table->string('source_glosa_normalized', 512);
            $table->string('target_value', 255);
            $table->string('rule_kind', 16)->default('alias');
            $table->boolean('active')->default(true);
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_sync_run_issue_id')->nullable()
                ->constrained('cen_center_sync_run_issues')->nullOnDelete();
            $table->timestamps();

            $table->unique(['field_key', 'source_glosa_normalized'], 'cen_catalog_glosa_equiv_field_source_uq');
            $table->index(['field_key', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cen_catalog_glosa_equivalences');
    }
};
