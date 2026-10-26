<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('met_measurement_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cen_center_id')->constrained('cen_centers')->restrictOnDelete();
            $table->foreignId('year_id')->constrained('years')->restrictOnDelete();
            $table->foreignId('met_unit_type_id')->constrained('met_unit_types')->restrictOnDelete();
            $table->string('codigo', 64);
            $table->string('nombre', 200)->nullable();
            $table->boolean('activo')->default(true);
            $table->foreignId('cen_hie_dependency_id')->nullable()->constrained('cen_hie_dependencies')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cen_center_id', 'year_id', 'codigo'], 'uq_met_measurement_units_center_year_codigo');
            $table->index(['cen_center_id', 'year_id', 'activo'], 'idx_met_measurement_units_center_year_activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('met_measurement_units');
    }
};
