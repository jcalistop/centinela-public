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
        Schema::create('com_sensitivity_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('com_action_id')->constrained();
            $table->decimal('meta_numero', 5, 2);
            $table->text('valores');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('com_sensitivity_tables');
    }
};
