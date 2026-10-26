<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'met_establishment_responsibles';

    private const LEGACY_TABLE = 'met_establishment_appeal_designees';

    private const LEGACY_UNIQUE = 'uq_appeal_designee_center_year_type';

    private const CENTER_INDEX = 'idx_est_responsible_center_year_type';

    private const USER_UNIQUE = 'uq_est_responsible_user_year_type';

    public function up(): void
    {
        if (Schema::hasTable(self::LEGACY_TABLE)) {
            $this->assertNoUserAssignedToMultipleCenters(self::LEGACY_TABLE);
            Schema::rename(self::LEGACY_TABLE, self::TABLE);
        }

        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (! $this->indexExists(self::TABLE, self::CENTER_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->index(
                    ['cen_center_id', 'year_id', 'indicator_type_id'],
                    self::CENTER_INDEX,
                );
            });
        }

        if (! $this->indexExists(self::TABLE, self::USER_UNIQUE)) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(
                    ['user_id', 'year_id', 'indicator_type_id'],
                    self::USER_UNIQUE,
                );
            });
        }

        if (! $this->indexExists(self::TABLE, self::LEGACY_UNIQUE)) {
            return;
        }

        if ($this->isSqlite()) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropUnique(self::LEGACY_UNIQUE);
            });

            return;
        }

        Schema::disableForeignKeyConstraints();

        foreach (['cen_center_id', 'year_id', 'indicator_type_id'] as $column) {
            $this->dropForeignOnColumnIfExists(self::TABLE, $column);
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::LEGACY_UNIQUE);
        });

        $this->ensureForeignKey(
            self::TABLE,
            'cen_center_id',
            'cen_centers',
            'id',
            'restrict',
        );
        $this->ensureForeignKey(
            self::TABLE,
            'year_id',
            'years',
            'id',
            'restrict',
        );
        $this->ensureForeignKey(
            self::TABLE,
            'indicator_type_id',
            'indicator_types',
            'id',
            'cascade',
        );

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->indexExists(self::TABLE, self::LEGACY_UNIQUE)) {
            Schema::rename(self::TABLE, self::LEGACY_TABLE);

            return;
        }

        if ($this->isSqlite()) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                if ($this->indexExists(self::TABLE, self::CENTER_INDEX)) {
                    $table->dropIndex(self::CENTER_INDEX);
                }
            });

            Schema::table(self::TABLE, function (Blueprint $table): void {
                if ($this->indexExists(self::TABLE, self::USER_UNIQUE)) {
                    $table->dropUnique(self::USER_UNIQUE);
                }
            });

            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->unique(
                    ['cen_center_id', 'year_id', 'indicator_type_id'],
                    self::LEGACY_UNIQUE,
                );
            });

            Schema::rename(self::TABLE, self::LEGACY_TABLE);

            return;
        }

        Schema::disableForeignKeyConstraints();

        foreach (['cen_center_id', 'year_id', 'indicator_type_id', 'user_id'] as $column) {
            $this->dropForeignOnColumnIfExists(self::TABLE, $column);
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            if ($this->indexExists(self::TABLE, self::CENTER_INDEX)) {
                $table->dropIndex(self::CENTER_INDEX);
            }
            if ($this->indexExists(self::TABLE, self::USER_UNIQUE)) {
                $table->dropUnique(self::USER_UNIQUE);
            }
            $table->unique(
                ['cen_center_id', 'year_id', 'indicator_type_id'],
                self::LEGACY_UNIQUE,
            );
        });

        $this->ensureForeignKey(self::TABLE, 'cen_center_id', 'cen_centers', 'id', 'restrict');
        $this->ensureForeignKey(self::TABLE, 'year_id', 'years', 'id', 'restrict');
        $this->ensureForeignKey(self::TABLE, 'indicator_type_id', 'indicator_types', 'id', 'cascade');
        $this->ensureForeignKey(self::TABLE, 'user_id', 'users', 'id', 'restrict');

        Schema::enableForeignKeyConstraints();

        Schema::rename(self::TABLE, self::LEGACY_TABLE);
    }

    private function assertNoUserAssignedToMultipleCenters(string $table): void
    {
        $conflicts = DB::table($table)
            ->select('user_id', 'year_id', 'indicator_type_id')
            ->selectRaw('COUNT(*) AS centros')
            ->groupBy('user_id', 'year_id', 'indicator_type_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($conflicts->isNotEmpty()) {
            throw new RuntimeException(
                'SDD-204: no se puede migrar — usuarios designados en más de un centro: '
                    .json_encode($conflicts->toArray())
            );
        }
    }

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

    private function isSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }

    private function dropForeignOnColumnIfExists(string $table, string $column): void
    {
        $constraintName = $this->foreignKeyNameOnColumn($table, $column);

        if ($constraintName === null) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`");
    }

    private function foreignKeyNameOnColumn(string $table, string $column): ?string
    {
        $database = Schema::getConnection()->getDatabaseName();

        $rows = DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$database, $table, $column],
        );

        if ($rows === []) {
            return null;
        }

        return (string) $rows[0]->CONSTRAINT_NAME;
    }

    private function ensureForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $onDelete,
    ): void {
        if ($this->foreignKeyNameOnColumn($table, $column) !== null) {
            return;
        }

        $onDeleteSql = match ($onDelete) {
            'cascade' => 'ON DELETE CASCADE',
            'restrict' => 'ON DELETE RESTRICT',
            default => 'ON DELETE RESTRICT',
        };

        DB::statement(
            "ALTER TABLE `{$table}`
             ADD CONSTRAINT `{$table}_{$column}_foreign`
             FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`{$referencedColumn}`)
             {$onDeleteSql}",
        );
    }
};
