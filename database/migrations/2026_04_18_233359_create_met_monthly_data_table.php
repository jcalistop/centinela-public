<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('met_monthly_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('met_data_field_id')->constrained('met_data_fields')->cascadeOnDelete();
            $table->foreignId('cen_center_id')->constrained('cen_centers');
            $table->foreignId('cen_hie_dependency_id')->constrained('cen_hie_dependencies');
            $table->foreignId('year_id')->constrained('years');
            $table->unsignedTinyInteger('mes')->nullable();
            $table->decimal('valor', 15, 4)->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['met_data_field_id', 'cen_center_id', 'year_id', 'mes'], 'uq_met_monthly');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('met_monthly_data');
    }
};
