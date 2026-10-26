<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cen_centers')) {
            return;
        }

        Schema::table('cen_centers', function (Blueprint $table) {
            if (! Schema::hasColumn('cen_centers', 'cen_type')) {
                $table->string('cen_type', 255)->nullable();
            }
            if (! Schema::hasColumn('cen_centers', 'cen_scope')) {
                $table->string('cen_scope', 255)->nullable();
            }
            if (! Schema::hasColumn('cen_centers', 'cen_certification')) {
                $table->string('cen_certification', 255)->nullable();
            }
            if (! Schema::hasColumn('cen_centers', 'cen_adm_dependency')) {
                $table->string('cen_adm_dependency', 255)->nullable();
            }
            if (! Schema::hasColumn('cen_centers', 'cen_emergency_type')) {
                $table->string('cen_emergency_type', 255)->nullable();
            }
            if (! Schema::hasColumn('cen_centers', 'cen_provider_type')) {
                $table->string('cen_provider_type', 255)->nullable();
            }
        });

        $this->copyCatalogName('cen_types', 'cen_type_id', 'cen_type');
        $this->copyCatalogName('cen_scopes', 'cen_scope_id', 'cen_scope');
        $this->copyCatalogName('cen_certifications', 'cen_certification_id', 'cen_certification');
        $this->copyCatalogName('cen_adm_dependencies', 'cen_adm_dependency_id', 'cen_adm_dependency');
        $this->copyCatalogName('cen_emergency_types', 'cen_emergency_type_id', 'cen_emergency_type');
        $this->copyCatalogName('cen_provider_types', 'cen_provider_type_id', 'cen_provider_type');

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->nullableCenCenterCatalogForeignKeyColumnsForSqlite();

            return;
        }

        if ($driver !== 'mysql') {
            return;
        }

        $this->dropCenCenterCatalogForeignKeyColumns();
        $this->dropCenCatalogTables();
    }

    /**
     * SQLite conserva las columnas (los tests las siguen poblando), pero sin FK:
     * los catálogos quedan sin mantención tras esta migración.
     */
    private function nullableCenCenterCatalogForeignKeyColumnsForSqlite(): void
    {
        foreach (['cen_type_id', 'cen_scope_id', 'cen_certification_id', 'cen_adm_dependency_id', 'cen_emergency_type_id', 'cen_provider_type_id'] as $column) {
            if (! Schema::hasColumn('cen_centers', $column)) {
                continue;
            }

            Schema::table('cen_centers', function (Blueprint $table) use ($column) {
                $table->dropForeign([$column]);
            });

            Schema::table('cen_centers', function (Blueprint $table) use ($column) {
                $table->unsignedBigInteger($column)->nullable()->change();
            });
        }
    }

    private function dropCenCenterCatalogForeignKeyColumns(): void
    {
        foreach (['cen_type_id', 'cen_scope_id', 'cen_certification_id', 'cen_adm_dependency_id', 'cen_emergency_type_id', 'cen_provider_type_id'] as $column) {
            if (! Schema::hasColumn('cen_centers', $column)) {
                continue;
            }

            Schema::table('cen_centers', function (Blueprint $table) use ($column) {
                $table->dropForeign([$column]);
            });

            Schema::table('cen_centers', function (Blueprint $table) use ($column) {
                $table->dropColumn($column);
            });
        }
    }

    private function dropCenCatalogTables(): void
    {
        Schema::dropIfExists('cen_types');
        Schema::dropIfExists('cen_scopes');
        Schema::dropIfExists('cen_certifications');
        Schema::dropIfExists('cen_emergency_types');
        Schema::dropIfExists('cen_provider_types');
        Schema::dropIfExists('cen_adm_dependencies');
    }

    private function copyCatalogName(string $catalogTable, string $foreignKeyColumn, string $targetColumn): void
    {
        if (! Schema::hasTable($catalogTable) || ! Schema::hasColumn('cen_centers', $foreignKeyColumn)) {
            return;
        }

        DB::statement(
            "UPDATE cen_centers SET {$targetColumn} = (SELECT nombre FROM {$catalogTable} WHERE {$catalogTable}.id = cen_centers.{$foreignKeyColumn})"
        );
    }

    public function down(): void
    {
        // No reversible: recrear catálogos y FKs exigiría backfill manual.
    }
};
