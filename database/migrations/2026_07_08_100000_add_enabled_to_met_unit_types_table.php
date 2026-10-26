<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-154: columna `enabled` para habilitar/deshabilitar tipos de unidad de Metas.
 * Migración aditiva (la de creación ya está aplicada en producción).
 */
return new class extends Migration
{
    private const TABLE_NAME = 'met_unit_types';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE_NAME) || Schema::hasColumn(self::TABLE_NAME, 'enabled')) {
            return;
        }

        Schema::table(self::TABLE_NAME, function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('orden');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE_NAME) || ! Schema::hasColumn(self::TABLE_NAME, 'enabled')) {
            return;
        }

        Schema::table(self::TABLE_NAME, function (Blueprint $table) {
            $table->dropColumn('enabled');
        });
    }
};
