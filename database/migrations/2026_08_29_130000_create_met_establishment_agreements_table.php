<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('met_establishment_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cen_center_id')->constrained('cen_centers')->cascadeOnDelete();
            $table->foreignId('year_id')->constrained('years')->cascadeOnDelete();
            $table->foreignId('indicator_type_id')->constrained('indicator_types')->cascadeOnDelete();
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies')->cascadeOnDelete();
            $table->string('estado', 20)->default('BORRADOR');
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_motivo')->nullable();
            $table->timestamps();

            $table->unique(
                ['cen_center_id', 'year_id', 'indicator_type_id'],
                'uq_met_establishment_agreement',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('met_establishment_agreements');
    }
};
