<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_ambito_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('indicator_ambito_id')
                ->constrained('indicator_ambitos')
                ->cascadeOnDelete();
            $table->string('indicatable_type');
            $table->unsignedBigInteger('indicatable_id');
            $table->index(['indicatable_type', 'indicatable_id'], 'ind_ambito_morph_idx');
            $table->timestamps();

            $table->unique(
                ['indicator_ambito_id', 'indicatable_type', 'indicatable_id'],
                'ind_ambito_assign_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_ambito_assignments');
    }
};
