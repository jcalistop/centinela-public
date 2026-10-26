<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('met_field_ss_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('met_data_field_id')->constrained('met_data_fields')->cascadeOnDelete();
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies');
            $table->foreignId('year_id')->constrained('years');
            $table->string('input_type', 20);
            $table->string('aggregation_type', 20)->nullable();
            $table->timestamps();

            $table->unique(
                ['met_data_field_id', 'cen_hie_dependency_id', 'year_id'],
                'uq_field_ss_year'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('met_field_ss_configs');
    }
};
