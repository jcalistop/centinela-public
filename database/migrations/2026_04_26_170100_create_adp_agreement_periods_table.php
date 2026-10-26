<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_agreement_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_agreement_id')
                ->constrained('adp_agreements')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();

            $table->unique(['adp_agreement_id', 'sequence'], 'adp_agrmnt_prd_seq_uq');
            $table->index('adp_agreement_id', 'adp_agrmnt_prd_agr_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_agreement_periods');
    }
};
