<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('met_center_inclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cen_center_id')->constrained('cen_centers')->restrictOnDelete();
            $table->foreignId('year_id')->constrained('years')->restrictOnDelete();
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['cen_center_id', 'year_id'], 'uq_met_center_inclusion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('met_center_inclusions');
    }
};
