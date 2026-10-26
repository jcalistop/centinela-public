<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('com_sensitivity_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('com_sensitivity_table_id')->constrained()->onDelete('cascade');
            $table->foreignId('com_action_id')->constrained()->onDelete('cascade');
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('com_sensitivity_dependencies');
    }
};
