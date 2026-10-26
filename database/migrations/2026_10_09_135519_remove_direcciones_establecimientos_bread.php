<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD-267 — retira BREAD Voyager `direcciones-establecimientos` (tabla lógica
 * `cen_addresses`). No elimina la tabla de negocio `cen_addresses` ni sus filas.
 *
 * Flujo sustituto: `/admin/establecimientos` (CenCenterController + CenAddress).
 * Idempotente: no-op si el data_type no está.
 */
return new class extends Migration
{
    private const DATA_TYPE_NAME = 'cen_addresses';

    private const DATA_TYPE_SLUG = 'direcciones-establecimientos';

    public function up(): void
    {
        if (! Schema::hasTable('data_types')) {
            return;
        }

        $dataTypeIds = DB::table('data_types')
            ->where(function ($query): void {
                $query->where('name', self::DATA_TYPE_NAME)
                    ->orWhere('slug', self::DATA_TYPE_SLUG);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($dataTypeIds === []) {
            return;
        }

        if (Schema::hasTable('data_rows')) {
            DB::table('data_rows')->whereIn('data_type_id', $dataTypeIds)->delete();
        }

        if (Schema::hasTable('translations')) {
            DB::table('translations')
                ->where('table_name', 'data_types')
                ->whereIn('foreign_key', $dataTypeIds)
                ->delete();
        }

        if (Schema::hasTable('permissions')) {
            $permissionIds = DB::table('permissions')
                ->where('table_name', self::DATA_TYPE_NAME)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($permissionIds !== [] && Schema::hasTable('permission_role')) {
                DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            }

            DB::table('permissions')->where('table_name', self::DATA_TYPE_NAME)->delete();
        }

        if (Schema::hasTable('menu_items')) {
            DB::table('menu_items')
                ->where(function ($query): void {
                    $query->where('route', 'voyager.'.self::DATA_TYPE_SLUG.'.index')
                        ->orWhere('url', 'like', '%/'.self::DATA_TYPE_SLUG.'%');
                })
                ->delete();
        }

        DB::table('data_types')->whereIn('id', $dataTypeIds)->delete();
    }

    public function down(): void
    {
        // Irreversible: AddressController y BREAD legacy se retiran del código (SDD-267).
        // Recrear a mano solo si ops lo exige; el flujo canónico es establecimientos.
    }
};
