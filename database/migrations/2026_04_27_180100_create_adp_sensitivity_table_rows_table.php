<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adp_sensitivity_table_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('adp_sensitivity_table_id')->constrained('adp_sensitivity_tables')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->decimal('valor_minimo', 12, 4)->nullable();
            $table->enum('operador_minimo', ['>=', '>'])->default('>=');
            $table->decimal('valor_maximo', 12, 4)->nullable();
            $table->enum('operador_maximo', ['<=', '<'])->default('<');
            $table->decimal('cumplimiento', 5, 2);
            $table->timestamps();

            $table->index(['adp_sensitivity_table_id', 'orden'], 'adp_sens_row_tbl_ord');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adp_sensitivity_table_rows');
    }
};
