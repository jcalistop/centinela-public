<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'uq_com_ind_set_user';

    private const COMPOUND_INDEX = 'idx_com_ind_set_user_compound';

    public function up(): void
    {
        if (! Schema::hasTable('com_indicator_setting_user')) {
            return;
        }

        $this->dedupePivotRows();

        $remainingDuplicates = $this->countDuplicateExtraRows();

        if ($remainingDuplicates > 0) {
            throw new RuntimeException(
                'com_indicator_setting_user aún tiene pares duplicados tras dedupe; no se crea UNIQUE.'
            );
        }

        if ($this->hasIndex(self::UNIQUE_INDEX)) {
            return;
        }

        if ($this->hasIndex(self::COMPOUND_INDEX)) {
            Schema::table('com_indicator_setting_user', function (Blueprint $table): void {
                $table->dropIndex(self::COMPOUND_INDEX);
            });
        }

        Schema::table('com_indicator_setting_user', function (Blueprint $table): void {
            $table->unique(['com_indicator_setting_id', 'user_id'], self::UNIQUE_INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('com_indicator_setting_user')) {
            return;
        }

        if ($this->hasIndex(self::UNIQUE_INDEX)) {
            Schema::table('com_indicator_setting_user', function (Blueprint $table): void {
                $table->dropUnique(self::UNIQUE_INDEX);
            });
        }

        if (! $this->hasIndex(self::COMPOUND_INDEX)) {
            Schema::table('com_indicator_setting_user', function (Blueprint $table): void {
                $table->index(['com_indicator_setting_id', 'user_id'], self::COMPOUND_INDEX);
            });
        }
    }

    private function dedupePivotRows(): void
    {
        $pairs = DB::table('com_indicator_setting_user')
            ->select('com_indicator_setting_id', 'user_id')
            ->distinct()
            ->orderBy('com_indicator_setting_id')
            ->orderBy('user_id')
            ->get();

        $totalRows = (int) DB::table('com_indicator_setting_user')->count();

        if ($totalRows === $pairs->count()) {
            return;
        }

        DB::table('com_indicator_setting_user')->delete();

        foreach ($pairs->chunk(500) as $chunk) {
            DB::table('com_indicator_setting_user')->insert(
                $chunk->map(fn ($row): array => [
                    'com_indicator_setting_id' => $row->com_indicator_setting_id,
                    'user_id' => $row->user_id,
                ])->all()
            );
        }
    }

    private function countDuplicateExtraRows(): int
    {
        $driver = Schema::getConnection()->getDriverName();
        $distinctExpr = $driver === 'sqlite'
            ? "com_indicator_setting_id || '-' || user_id"
            : "CONCAT(com_indicator_setting_id, '-', user_id)";

        return (int) DB::table('com_indicator_setting_user')
            ->selectRaw("COUNT(*) - COUNT(DISTINCT {$distinctExpr}) as extra")
            ->value('extra');
    }

    private function hasIndex(string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('com_indicator_setting_user')");

            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $rows = DB::select('SHOW INDEX FROM `com_indicator_setting_user` WHERE Key_name = ?', [$indexName]);

        return $rows !== [];
    }
};
