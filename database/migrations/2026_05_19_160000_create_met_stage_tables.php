<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('met_establishment_appeal_designees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cen_center_id')->constrained('cen_centers')->restrictOnDelete();
            $table->foreignId('year_id')->constrained('years')->restrictOnDelete();
            $table->foreignId('indicator_type_id')->constrained('indicator_types')->cascadeOnDelete();
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['cen_center_id', 'year_id', 'indicator_type_id'],
                'uq_appeal_designee_center_year_type'
            );
        });

        Schema::create('met_indicator_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('met_indicator_id')->constrained('met_indicators')->cascadeOnDelete();
            $table->foreignId('cen_center_id')->constrained('cen_centers')->restrictOnDelete();
            $table->foreignId('year_id')->constrained('years')->restrictOnDelete();
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies');
            $table->string('appeal_type', 20);
            $table->text('motivo');
            $table->json('archivos')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['met_indicator_id', 'cen_center_id', 'year_id'],
                'uq_indicator_appeal_indicator_center_year'
            );
            $table->index(
                ['cen_center_id', 'year_id', 'cen_hie_dependency_id'],
                'idx_indicator_appeal_center_year_dep'
            );
        });

        Schema::create('met_preliminary_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('met_indicator_id')->constrained('met_indicators')->cascadeOnDelete();
            $table->foreignId('cen_center_id')->constrained('cen_centers')->restrictOnDelete();
            $table->foreignId('year_id')->constrained('years')->restrictOnDelete();
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies');
            $table->decimal('numerador', 15, 4)->nullable();
            $table->decimal('denominador', 15, 4)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['met_indicator_id', 'cen_center_id', 'year_id'],
                'uq_preliminary_eval_indicator_center_year'
            );
        });

        Schema::create('met_final_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('met_indicator_id')->constrained('met_indicators')->cascadeOnDelete();
            $table->foreignId('cen_center_id')->constrained('cen_centers')->restrictOnDelete();
            $table->foreignId('year_id')->constrained('years')->restrictOnDelete();
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies');
            $table->decimal('numerador', 15, 4)->nullable();
            $table->decimal('denominador', 15, 4)->nullable();
            $table->boolean('apelacion_acogida')->nullable();
            $table->text('resolucion_notas')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['met_indicator_id', 'cen_center_id', 'year_id'],
                'uq_final_eval_indicator_center_year'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('met_final_evaluations');
        Schema::dropIfExists('met_preliminary_evaluations');
        Schema::dropIfExists('met_indicator_appeals');
        Schema::dropIfExists('met_establishment_appeal_designees');
    }
};
