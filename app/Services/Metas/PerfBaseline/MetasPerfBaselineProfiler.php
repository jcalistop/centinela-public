<?php

namespace App\Services\Metas\PerfBaseline;

use Illuminate\Support\Facades\DB;

class MetasPerfBaselineProfiler
{
    /**
     * @param  callable(): mixed  $callback
     * @return array{wall_ms: float, query_count: int, peak_memory_mb: float, top_tables: array<string, int>, result: mixed}
     */
    public function profile(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $memBefore = memory_get_usage(true);
        $started = hrtime(true);

        $result = $callback();

        $wallMs = (hrtime(true) - $started) / 1_000_000;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        DB::flushQueryLog();

        $peakMb = max(memory_get_peak_usage(true), memory_get_usage(true), $memBefore) / (1024 * 1024);

        return [
            'wall_ms' => round($wallMs, 2),
            'query_count' => count($queries),
            'peak_memory_mb' => round($peakMb, 2),
            'top_tables' => $this->aggregateTopTables($queries, 10),
            'result' => $result,
        ];
    }

    /**
     * @param  array<int, array{query?: string}>  $queries
     * @return array<string, int>
     */
    private function aggregateTopTables(array $queries, int $limit): array
    {
        $counts = [];

        foreach ($queries as $entry) {
            $sql = (string) ($entry['query'] ?? '');
            if ($sql === '') {
                continue;
            }

            if (preg_match('/(?:from|join|update|into)\s+[`"\[]?([a-z0-9_]+)/i', $sql, $matches) !== 1) {
                continue;
            }

            $table = strtolower($matches[1]);
            $counts[$table] = ($counts[$table] ?? 0) + 1;
        }

        arsort($counts);

        return array_slice($counts, 0, $limit, true);
    }
}
