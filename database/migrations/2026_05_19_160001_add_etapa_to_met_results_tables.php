<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MET_RESULTS_INDICATOR_IDX = 'idx_met_results_etapa_migration_indicator_id';

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

            return count($rows) > 0;
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");

            foreach ($rows as $row) {
                if ((string) ($row->name ?? '') === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    private function dropUniqueIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropUnique($indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    private function isSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }

    private function ensureMetResultsIndicatorIdIndex(): void
    {
        if ($this->indexExists('met_results', self::MET_RESULTS_INDICATOR_IDX)) {
            return;
        }

        Schema::table('met_results', function (Blueprint $table) {
            $table->index('met_indicator_id', self::MET_RESULTS_INDICATOR_IDX);
        });
    }

    private function rebuildMetResultsUniqueIndexes(): void
    {
        if (! $this->isSqlite()) {
            $this->ensureMetResultsIndicatorIdIndex();
        }

        $this->dropUniqueIfExists('met_results', 'uq_met_result_per_unit_scope');
        $this->dropUniqueIfExists('met_results', 'uq_met_result');

        if (! $this->indexExists('met_results', 'uq_met_result_per_unit_scope_etapa')) {
            Schema::table('met_results', function (Blueprint $table) {
                $table->unique(
                    ['met_indicator_id', 'cen_center_id', 'year_id', 'periodo', 'result_unit_scope_key', 'etapa'],
                    'uq_met_result_per_unit_scope_etapa'
                );
            });
        }

        if (! $this->isSqlite()) {
            $this->dropIndexIfExists('met_results', self::MET_RESULTS_INDICATOR_IDX);
        }
    }

    private function rebuildMetEstablishmentResultsUniqueIndexes(): void
    {
        if (! $this->isSqlite()) {
            Schema::disableForeignKeyConstraints();

            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->dropForeign(['cen_center_id']);
                $table->dropForeign(['cen_hie_dependency_id']);
                $table->dropForeign(['year_id']);
                $table->dropForeign(['indicator_type_id']);
            });
        }

        $this->dropUniqueIfExists('met_establishment_results', 'uq_met_estab_result_per_instrument');
        $this->dropUniqueIfExists('met_establishment_results', 'uq_met_estab_result');

        if (! $this->indexExists('met_establishment_results', 'uq_met_estab_result_per_instrument_etapa')) {
            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->unique(
                    ['cen_center_id', 'year_id', 'periodo', 'indicator_type_id', 'etapa'],
                    'uq_met_estab_result_per_instrument_etapa'
                );
            });
        }

        if (! $this->isSqlite()) {
            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->foreign('cen_center_id')->references('id')->on('cen_centers');
                $table->foreign('cen_hie_dependency_id')->references('id')->on('cen_hie_dependencies');
                $table->foreign('year_id')->references('id')->on('years');
                $table->foreign('indicator_type_id')->references('id')->on('indicator_types');
            });

            Schema::enableForeignKeyConstraints();
        }
    }

    private function restoreMetResultsLegacyUniqueIndexes(): void
    {
        if (! $this->isSqlite()) {
            $this->ensureMetResultsIndicatorIdIndex();
        }

        $this->dropUniqueIfExists('met_results', 'uq_met_result_per_unit_scope_etapa');

        if (! $this->indexExists('met_results', 'uq_met_result_per_unit_scope')) {
            Schema::table('met_results', function (Blueprint $table) {
                $table->unique(
                    ['met_indicator_id', 'cen_center_id', 'year_id', 'periodo', 'result_unit_scope_key'],
                    'uq_met_result_per_unit_scope'
                );
            });
        }

        if ($this->isSqlite() && ! $this->indexExists('met_results', 'uq_met_result')) {
            Schema::table('met_results', function (Blueprint $table) {
                $table->unique(
                    ['met_indicator_id', 'cen_center_id', 'year_id', 'periodo'],
                    'uq_met_result'
                );
            });
        }

        if (! $this->isSqlite()) {
            $this->dropIndexIfExists('met_results', self::MET_RESULTS_INDICATOR_IDX);
        }
    }

    private function restoreMetEstablishmentResultsLegacyUniqueIndexes(): void
    {
        if (! $this->isSqlite()) {
            Schema::disableForeignKeyConstraints();

            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->dropForeign(['cen_center_id']);
                $table->dropForeign(['cen_hie_dependency_id']);
                $table->dropForeign(['year_id']);
                $table->dropForeign(['indicator_type_id']);
            });
        }

        $this->dropUniqueIfExists('met_establishment_results', 'uq_met_estab_result_per_instrument_etapa');

        if (! $this->indexExists('met_establishment_results', 'uq_met_estab_result_per_instrument')) {
            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->unique(
                    ['cen_center_id', 'year_id', 'periodo', 'indicator_type_id'],
                    'uq_met_estab_result_per_instrument'
                );
            });
        }

        if (! $this->isSqlite()) {
            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->foreign('cen_center_id')->references('id')->on('cen_centers');
                $table->foreign('cen_hie_dependency_id')->references('id')->on('cen_hie_dependencies');
                $table->foreign('year_id')->references('id')->on('years');
                $table->foreign('indicator_type_id')->references('id')->on('indicator_types');
            });

            Schema::enableForeignKeyConstraints();
        }
    }

    public function up(): void
    {
        if (! Schema::hasColumn('met_results', 'etapa')) {
            Schema::table('met_results', function (Blueprint $table) {
                $table->string('etapa', 20)->default('AUTOEVALUACION')->after('year_id');
            });
        }

        if (! Schema::hasColumn('met_establishment_results', 'etapa')) {
            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->string('etapa', 20)->default('AUTOEVALUACION')->after('indicator_type_id');
            });
        }

        $this->rebuildMetResultsUniqueIndexes();
        $this->rebuildMetEstablishmentResultsUniqueIndexes();
    }

    public function down(): void
    {
        $this->restoreMetResultsLegacyUniqueIndexes();
        $this->restoreMetEstablishmentResultsLegacyUniqueIndexes();

        if (Schema::hasColumn('met_results', 'etapa')) {
            Schema::table('met_results', function (Blueprint $table) {
                $table->dropColumn('etapa');
            });
        }

        if (Schema::hasColumn('met_establishment_results', 'etapa')) {
            Schema::table('met_establishment_results', function (Blueprint $table) {
                $table->dropColumn('etapa');
            });
        }
    }
};
