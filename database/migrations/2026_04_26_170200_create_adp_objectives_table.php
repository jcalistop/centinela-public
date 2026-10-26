<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_objectives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_agreement_period_id')
                ->constrained('adp_agreement_periods')
                ->cascadeOnDelete();
            $table->string('clave')->nullable();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('orden')->nullable();
            $table->timestamps();

            $table->index('adp_agreement_period_id', 'adp_obj_prd_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_objectives');
    }
};
