<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('met_stage_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_type_id')->constrained('indicator_types')->cascadeOnDelete();
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies')->cascadeOnDelete();
            $table->foreignId('year_id')->constrained('years')->cascadeOnDelete();
            $table->string('etapa_desde', 20)->nullable();
            $table->string('etapa_hasta', 20);
            $table->text('motivo')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['indicator_type_id', 'cen_hie_dependency_id', 'year_id', 'created_at'],
                'idx_met_stage_transitions_scope',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('met_stage_transitions');
    }
};
