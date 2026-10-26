<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_position_assignment_id')
                ->constrained('adp_position_assignments')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('duration_years');
            $table->date('agreement_start');
            $table->string('status', 16)->default('draft');
            $table->unsignedBigInteger('cen_hie_dependency_id');
            $table->timestamps();

            $table->index('cen_hie_dependency_id', 'adp_agrmnts_hie_idx');
            $table->index('adp_position_assignment_id', 'adp_agrmnts_asgmt_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_agreements');
    }
};
