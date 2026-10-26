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
        Schema::create('com_indicator_setting_user', function (Blueprint $table) {
            $table->bigInteger('com_indicator_setting_id');
            $table->bigInteger('user_id');

            $table->index(['com_indicator_setting_id', 'user_id'], 'idx_com_ind_set_user_compound');
            $table->index('com_indicator_setting_id', 'idx_com_ind_set_user_setting');
            $table->index('user_id', 'idx_com_ind_set_user_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('com_indicator_setting_user');
    }
};
