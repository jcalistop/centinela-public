<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicator_type_global_settings', function (Blueprint $table): void {
            $table->decimal('semaforo_verde_min', 5, 2)->nullable()
                ->after('max_ponderacion')
                ->comment('Umbral verde del semáforo global (%). NULL = herencia por route');
            $table->decimal('semaforo_amarillo_min', 5, 2)->nullable()
                ->after('semaforo_verde_min')
                ->comment('Umbral amarillo del semáforo global (%). NULL = herencia por route');
        });
    }

    public function down(): void
    {
        Schema::table('indicator_type_global_settings', function (Blueprint $table): void {
            $table->dropColumn(['semaforo_verde_min', 'semaforo_amarillo_min']);
        });
    }
};
