<?php

namespace App\Support\Admin;

use App\Models\CenHieDependency;
use App\Models\PlatformAreaUsageSample;
use App\Models\PlatformAuthSession;
use App\Models\Role;
use App\Models\User;
use App\Support\Health\HealthStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminUsageDashboardService
{
    private const USAGE_TABS = ['uso', 'tablas', 'salud', 'operativo'];

    private const CHARTS_CACHE_KEY = 'centinela:usage_dashboard:charts:v2';

    private const TABLES_CACHE_KEY = 'centinela:usage_dashboard:tables:v3';

    private const HEALTH_CACHE_KEY = 'centinela:system_health:v2';

    private const RETENTION_OPS_CACHE_KEY = 'centinela:retention_ops:v1';

    private const RECENT_USERS_LIMIT = 20;

    public function resolveTab(mixed $tab): string
    {
        if (! is_string($tab) || ! in_array($tab, self::USAGE_TABS, true)) {
            return 'uso';
        }

        return $tab;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function chartsPayload(): ?array
    {
        if (! (bool) config('centinela.usage_tracking.enabled', false)) {
            return null;
        }

        return Cache::remember(self::CHARTS_CACHE_KEY, now()->addMinutes(5), function (): array {
            $since7 = now()->subDays(7);
            $since30 = now()->subDays(30);
            $adminUserIds = $this->adminUserIds();

            $dailySummary7 = $this->dailySummary($since7, $adminUserIds);
            $dailySummary30 = $this->dailySummary($since30, $adminUserIds);

            $areaStackRows7 = $this->usageSamplesQuery($since7, $adminUserIds)
                ->selectRaw('date(created_at) as day, area, count(*) as hits')
                ->groupBy('day', 'area')
                ->orderBy('day')
                ->get()
                ->map(
                    fn ($row) => [
                        'day' => (string) $row->day,
                        'area' => (string) $row->area,
                        'hits' => (int) $row->hits,
                    ],
                )
                ->values()
                ->all();

            $areaStackCategories7 = array_map(static fn (array $row): string => $row['day'], $dailySummary7);
            $areaStackAreas7 = array_values(
                array_unique(array_map(static fn (array $row): string => $row['area'], $areaStackRows7)),
            );
            sort($areaStackAreas7);

            $areaStackIndex7 = [];
            foreach ($areaStackRows7 as $row) {
                $areaStackIndex7[$row['area']][$row['day']] = $row['hits'];
            }

            $areaStackSeries7 = array_map(static function (string $area) use (
                $areaStackCategories7,
                $areaStackIndex7,
            ): array {
                return [
                    'name' => $area,
                    'data' => array_map(
                        static fn (string $day): int => (int) ($areaStackIndex7[$area][$day] ?? 0),
                        $areaStackCategories7,
                    ),
                ];
            }, $areaStackAreas7);

            return [
                'dailySummary30' => $dailySummary30,
                'areaStack7' => [
                    'categories' => $areaStackCategories7,
                    'series' => $areaStackSeries7,
                ],
            ];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function tablesPayload(): ?array
    {
        if (! (bool) config('centinela.usage_tracking.enabled', false)) {
            return null;
        }

        return Cache::remember(self::TABLES_CACHE_KEY, now()->addMinutes(5), function (): array {
            $since7 = now()->subDays(7);
            $since30 = now()->subDays(30);
            $adminUserIds = $this->adminUserIds();

            $areaTotals7 = $this->usageSamplesQuery($since7, $adminUserIds)
                ->selectRaw('area, count(*) as hits, count(distinct user_id) as users')
                ->groupBy('area')
                ->orderByDesc('hits')
                ->get()
                ->map(
                    fn ($row) => [
                        'area' => (string) $row->area,
                        'hits' => (int) $row->hits,
                        'users' => (int) $row->users,
                    ],
                )
                ->values()
                ->all();

            $dailyUsers30 = $this->usageSamplesQuery($since30, $adminUserIds)
                ->selectRaw('date(created_at) as day, count(distinct user_id) as users')
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(
                    fn ($row) => [
                        'day' => (string) $row->day,
                        'users' => (int) $row->users,
                    ],
                )
                ->values()
                ->all();

            $topSsRows = $this->usageSamplesQuery($since7, $adminUserIds)
                ->whereNotNull('cen_hie_dependency_id')
                ->selectRaw('cen_hie_dependency_id, count(distinct user_id) as users')
                ->groupBy('cen_hie_dependency_id')
                ->orderByDesc('users')
                ->limit(10)
                ->get();

            $ssIds = $topSsRows
                ->map(static fn ($row): int => (int) $row->cen_hie_dependency_id)
                ->unique()
                ->values()
                ->all();

            $ssNamesById = $ssIds === []
                ? []
                : CenHieDependency::query()
                    ->whereIn('id', $ssIds)
                    ->get(['id', 'nombre'])
                    ->keyBy('id')
                    ->map(static fn (CenHieDependency $ss): string => (string) $ss->nombre)
                    ->all();

            $topSs7 = $topSsRows
                ->map(function ($row) use ($ssNamesById): array {
                    $id = (int) $row->cen_hie_dependency_id;

                    return [
                        'id' => $id,
                        'name' => $ssNamesById[$id] ?? 'SS #'.$id,
                        'users' => (int) $row->users,
                    ];
                })
                ->values()
                ->all();

            $authSessionsOpen = PlatformAuthSession::query()
                ->whereNull('ended_at')
                ->when($adminUserIds !== [], static fn ($query) => $query->whereNotIn('user_id', $adminUserIds))
                ->count();

            return [
                'areaTotals7' => $areaTotals7,
                'dailyUsers30' => $dailyUsers30,
                'topSs7' => $topSs7,
                'recentUsers7' => $this->recentActiveUsers($since7, $adminUserIds),
                'authSessionsOpen' => (int) $authSessionsOpen,
            ];
        });
    }

    /**
     * @param  list<int>  $adminUserIds
     * @return list<array{user_id: int, name: string, ss_id: int|null, ss_name: string|null, last_seen_at: string}>
     */
    private function recentActiveUsers(Carbon $since, array $adminUserIds): array
    {
        $rows = $this->usageSamplesQuery($since, $adminUserIds)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, max(created_at) as last_seen_at')
            ->groupBy('user_id')
            ->orderByDesc('last_seen_at')
            ->limit(self::RECENT_USERS_LIMIT)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $userIds = $rows->map(static fn ($row): int => (int) $row->user_id)->all();
        $users = User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'name', 'cen_hie_dependency_id'])
            ->keyBy('id');

        $ssIds = $users
            ->pluck('cen_hie_dependency_id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $ssById = $ssIds === []
            ? collect()
            : CenHieDependency::query()
                ->whereIn('id', $ssIds)
                ->get(['id', 'nombre'])
                ->keyBy('id');

        return $rows
            ->map(function ($row) use ($users, $ssById): array {
                $userId = (int) $row->user_id;
                $user = $users->get($userId);
                $ssId = $user?->cen_hie_dependency_id !== null
                    ? (int) $user->cen_hie_dependency_id
                    : null;

                return [
                    'user_id' => $userId,
                    'name' => (string) ($user?->name ?? 'Usuario #'.$userId),
                    'ss_id' => $ssId,
                    'ss_name' => $ssId !== null
                        ? (string) ($ssById->get($ssId)?->nombre ?? 'SS #'.$ssId)
                        : null,
                    'last_seen_at' => Carbon::parse($row->last_seen_at)->format('d-m-Y H:i'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function systemHealthPayload(): array
    {
        return Cache::remember(self::HEALTH_CACHE_KEY, now()->addMinutes(2), function (): array {
            return app(HealthStatusService::class)->status();
        });
    }

    /**
     * Agregados seguros de retención/prune para tab Salud (solo admin).
     *
     * @return array<string, mixed>
     */
    public function retentionOpsPayload(): array
    {
        return Cache::remember(self::RETENTION_OPS_CACHE_KEY, now()->addMinutes(5), function (): array {
            try {
                $failedJobsTotal = Schema::hasTable('failed_jobs')
                    ? (int) DB::table('failed_jobs')->count()
                    : null;

                return [
                    'failed_jobs_total' => $failedJobsTotal,
                    'failed_jobs_retention_hours' => (int) config('centinela.worker.failed_jobs_retention_hours', 168),
                    'support_close_resolved_days' => (int) config('centinela.support.close_resolved_days', 5),
                    'schedule_logs' => $this->directoryAggregate(storage_path('logs'), [
                        'db-backup.log',
                        'audit-cleanup.log',
                        'storage-temp-cleanup.log',
                        'schedule-logs-prune.log',
                        'security-audit.log',
                        'support-tickets-close-resolved.log',
                        'cen-sync-runs-prune.log',
                        'centinela-deploy-prune.log',
                        'platform-auth-sessions-close.log',
                        'centinela-deploy-auto.log',
                        'cen-establishments-sync.log',
                    ]),
                    'storage_temp' => $this->directoryAggregate(storage_path('temp')),
                    'storage_app_temp' => $this->directoryAggregate(storage_path('app/temp')),
                    'runbook_hint' => 'SDD-105 — auditoría prunes/purgas (docs SDD)',
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                return [
                    'failed_jobs_total' => null,
                    'failed_jobs_retention_hours' => (int) config('centinela.worker.failed_jobs_retention_hours', 168),
                    'support_close_resolved_days' => (int) config('centinela.support.close_resolved_days', 5),
                    'schedule_logs' => ['bytes' => null, 'files' => null, 'oldest_hours' => null, 'error' => true],
                    'storage_temp' => ['bytes' => null, 'files' => null, 'oldest_hours' => null, 'error' => true],
                    'storage_app_temp' => ['bytes' => null, 'files' => null, 'oldest_hours' => null, 'error' => true],
                    'runbook_hint' => 'SDD-105 — auditoría prunes/purgas (docs SDD)',
                    'error' => 'No se pudo leer métricas de retención',
                ];
            }
        });
    }

    /**
     * @param  list<string>|null  $onlyBasenames
     * @return array{bytes: int|null, files: int|null, oldest_hours: int|null, error: bool}
     */
    private function directoryAggregate(string $path, ?array $onlyBasenames = null): array
    {
        if (! is_dir($path) || ! is_readable($path)) {
            return ['bytes' => null, 'files' => null, 'oldest_hours' => null, 'error' => true];
        }

        $bytes = 0;
        $files = 0;
        $oldestMtime = null;
        $now = time();
        $maxFiles = 500;

        try {
            if ($onlyBasenames !== null) {
                foreach ($onlyBasenames as $basename) {
                    $file = $path.DIRECTORY_SEPARATOR.$basename;
                    if (! is_file($file)) {
                        continue;
                    }
                    $size = @filesize($file);
                    $mtime = @filemtime($file);
                    if ($size === false || $mtime === false) {
                        continue;
                    }
                    $bytes += (int) $size;
                    $files++;
                    $oldestMtime = $oldestMtime === null ? $mtime : min($oldestMtime, $mtime);
                }
            } else {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY,
                );

                foreach ($iterator as $fileInfo) {
                    if ($files >= $maxFiles) {
                        break;
                    }
                    if (! $fileInfo->isFile()) {
                        continue;
                    }
                    $bytes += (int) $fileInfo->getSize();
                    $files++;
                    $mtime = (int) $fileInfo->getMTime();
                    $oldestMtime = $oldestMtime === null ? $mtime : min($oldestMtime, $mtime);
                }
            }
        } catch (\Throwable) {
            return ['bytes' => null, 'files' => null, 'oldest_hours' => null, 'error' => true];
        }

        return [
            'bytes' => $bytes,
            'files' => $files,
            'oldest_hours' => $oldestMtime !== null ? (int) floor(($now - $oldestMtime) / 3600) : null,
            'error' => false,
        ];
    }

    /**
     * @param  list<int>  $adminUserIds
     * @return list<array{day: string, hits: int, users: int}>
     */
    private function dailySummary(Carbon $since, array $adminUserIds): array
    {
        return $this->usageSamplesQuery($since, $adminUserIds)
            ->selectRaw('date(created_at) as day, count(*) as hits, count(distinct user_id) as users')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(
                fn ($row) => [
                    'day' => (string) $row->day,
                    'hits' => (int) $row->hits,
                    'users' => (int) $row->users,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * Usuarios con rol admin (primario o pivot) — excluidos de métricas de uso (SDD-086 A).
     *
     * @return list<int>
     */
    private function adminUserIds(): array
    {
        $adminRoleId = Role::query()->where('name', 'admin')->value('id');
        if ($adminRoleId === null) {
            return [];
        }

        return User::query()
            ->where(function ($query) use ($adminRoleId): void {
                $query->where('role_id', $adminRoleId)
                    ->orWhereIn('id', function ($sub) use ($adminRoleId): void {
                        $sub->select('user_id')
                            ->from('user_roles')
                            ->where('role_id', $adminRoleId);
                    });
            })
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $adminUserIds
     */
    private function usageSamplesQuery(Carbon $since, array $adminUserIds): Builder
    {
        return PlatformAreaUsageSample::query()
            ->where('created_at', '>=', $since)
            ->when($adminUserIds !== [], static fn ($query) => $query->whereNotIn('user_id', $adminUserIds));
    }
}
