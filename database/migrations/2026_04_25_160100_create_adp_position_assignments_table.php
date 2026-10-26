<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_position_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('adp_position_id')->constrained('adp_positions')->cascadeOnDelete();
            $table->foreignId('cen_center_id')->constrained('cen_centers')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('cen_hie_dependency_id');
            $table->timestamps();

            $table->index('cen_hie_dependency_id', 'adp_pos_asg_dep_idx');
            $table->index(['user_id', 'cen_center_id', 'adp_position_id'], 'adp_pos_asg_usr_ctr_pos_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_position_assignments');
    }
};
