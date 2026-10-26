<?php

declare(strict_types=1);

namespace App\Support\Health;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HealthStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function status(bool $compact = false): array
    {
        $startTime = microtime(true);

        $appName = config('app.name');
        $dbOk = false;
        $dbError = null;

        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
            Log::warning('Health DB check failed', ['error' => $dbError]);
        }

        $schedulerStart = microtime(true);
        $scheduler = $this->schedulerStatus();
        $schedulerDuration = $this->durationMs($schedulerStart);

        $workerStart = microtime(true);
        $worker = $this->workerStatus();
        $workerDuration = $this->durationMs($workerStart);

        $result = [
            'status' => 'ok',
            'app' => $appName,
            'timestamp' => now()->toIso8601String(),
            'duration_ms' => $this->durationMs($startTime),
            'db' => [
                'ok' => $dbOk,
                'error' => $dbError,
                'duration_ms' => $this->durationMs($startTime),
            ],
            'queue' => [
                'connection' => config('queue.default'),
            ],
            'scheduler' => $scheduler + ['duration_ms' => $schedulerDuration],
            'worker' => $worker + ['duration_ms' => $workerDuration],
            'php_version' => PHP_VERSION,
        ];

        if ($compact) {
            // Modo compacto: omitir backup y security (reducen tiempo de respuesta ~50%)
            $result['backup'] = ['status' => 'omitted'];
            $result['security'] = ['status' => 'omitted'];
        } else {
            $backupStart = microtime(true);
            $result['backup'] = $this->backupStatus() + ['duration_ms' => $this->durationMs($backupStart)];
            $securityStart = microtime(true);
            $result['security'] = $this->securityStatus() + ['duration_ms' => $this->durationMs($securityStart)];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function schedulerStatus(): array
    {
        $heartbeatPath = storage_path('framework/scheduler-heartbeat.json');

        if (! file_exists($heartbeatPath)) {
            return [
                'status' => 'unknown',
                'last_run' => null,
                'minutes_ago' => null,
                'message' => 'No heartbeat file found',
            ];
        }

        try {
            /** @var array<string, mixed>|null $heartbeatData */
            $heartbeatData = json_decode((string) file_get_contents($heartbeatPath), true);

            if (! is_array($heartbeatData) || ! isset($heartbeatData['last_run'])) {
                return [
                    'status' => 'unknown',
                    'last_run' => null,
                    'minutes_ago' => null,
                    'message' => 'Invalid heartbeat data',
                ];
            }

            $lastRun = Carbon::parse((string) $heartbeatData['last_run']);
            $minutesAgo = now()->diffInMinutes($lastRun);
            $thresholdMinutes = (int) config(
                'centinela.scheduler.heartbeat_threshold_minutes',
                config('services.scheduler.heartbeat_threshold_minutes', 150),
            );

            $status = 'ok';
            if ($minutesAgo >= $thresholdMinutes) {
                $status = 'down';
            } elseif ($minutesAgo >= (int) round($thresholdMinutes * 0.7)) {
                $status = 'warning';
            }

            return [
                'status' => $status,
                'last_run' => $lastRun->toIso8601String(),
                'minutes_ago' => $minutesAgo,
                'threshold_minutes' => $thresholdMinutes,
                'hostname' => $heartbeatData['hostname'] ?? null,
                'environment' => $heartbeatData['environment'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Error al leer heartbeat del scheduler', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'last_run' => null,
                'minutes_ago' => null,
                'message' => 'Error reading heartbeat: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function workerStatus(): array
    {
        $staleMinutes = (int) config(
            'centinela.worker.stale_minutes',
            config('services.worker.stale_minutes', 30),
        );
        $warningJobs = (int) config(
            'centinela.worker.pending_warning_jobs',
            config('services.worker.pending_warning_jobs', 5),
        );
        $failedWarning = (int) config(
            'centinela.worker.failed_jobs_warning_24h',
            config('services.worker.failed_jobs_warning_24h', 5),
        );

        if (! Schema::hasTable('jobs')) {
            return [
                'status' => 'unknown',
                'pending_jobs' => null,
                'failed_jobs_24h' => null,
                'oldest_pending_job_minutes' => null,
                'oldest_queued_at' => null,
                'oldest_available_at' => null,
                'stale_threshold_minutes' => $staleMinutes,
                'message' => 'Tabla jobs no disponible',
            ];
        }

        $pendingJobs = (int) DB::table('jobs')->count();
        $failedJobs24h = Schema::hasTable('failed_jobs')
            ? (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count()
            : 0;

        $oldest = DB::table('jobs')
            ->select('available_at', 'created_at')
            ->orderBy('available_at')
            ->first();

        $oldestMinutes = null;
        $oldestQueuedAt = null;
        $oldestAvailableAt = null;

        if ($oldest && ! empty($oldest->available_at)) {
            $oldestTimestamp = (int) $oldest->available_at;
            $oldestMinutes = now()->diffInMinutes(Carbon::createFromTimestamp($oldestTimestamp));
            $oldestAvailableAt = Carbon::createFromTimestamp($oldestTimestamp)->toIso8601String();
            if (! empty($oldest->created_at)) {
                $oldestQueuedAt = Carbon::createFromTimestamp((int) $oldest->created_at)->toIso8601String();
            }
        } elseif ($oldest && ! empty($oldest->created_at)) {
            $oldestMinutes = now()->diffInMinutes(Carbon::createFromTimestamp((int) $oldest->created_at));
            $oldestQueuedAt = Carbon::createFromTimestamp((int) $oldest->created_at)->toIso8601String();
        }

        $status = 'ok';
        if ($pendingJobs > 0 && $oldestMinutes !== null && $oldestMinutes >= $staleMinutes) {
            $status = 'down';
        } elseif ($pendingJobs >= $warningJobs || $failedJobs24h >= $failedWarning) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'pending_jobs' => $pendingJobs,
            'failed_jobs_24h' => $failedJobs24h,
            'oldest_pending_job_minutes' => $oldestMinutes,
            'oldest_queued_at' => $oldestQueuedAt,
            'oldest_available_at' => $oldestAvailableAt,
            'stale_threshold_minutes' => $staleMinutes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function backupStatus(): array
    {
        $files = glob(storage_path('app/backups/backup_*')) ?: [];
        if (empty($files)) {
            return [
                'status' => 'missing',
                'last_success' => null,
                'last_file' => null,
                'minutes_since_last' => null,
            ];
        }

        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $latest = $files[0];
        $timestamp = filemtime($latest);
        $minutesSince = (int) floor((time() - (int) $timestamp) / 60);
        $maxAgeHours = (int) config('centinela.backup.max_age_hours', 24);

        return [
            'status' => $minutesSince <= ($maxAgeHours * 60) ? 'ok' : 'warning',
            'last_success' => now()->setTimestamp((int) $timestamp)->toDateTimeString(),
            'last_file' => basename($latest),
            'minutes_since_last' => $minutesSince,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function securityStatus(): array
    {
        $logPath = storage_path('logs/failed-logins.log');

        if (! file_exists($logPath)) {
            return [
                'status' => 'unknown',
                'failed_logins_24h' => null,
                'suspicious_ips' => [],
                'message' => 'Sin registros de intentos fallidos aún',
            ];
        }

        $lookbackHours = (int) config('centinela.security.failed_login_lookback_hours', 24);
        $threshold = (int) config('centinela.security.failed_login_threshold', 10);
        $since = now()->subHours($lookbackHours);
        $attemptsTotal = 0;
        $ipCounts = [];

        $handle = fopen($logPath, 'r');
        if ($handle !== false) {
            while (($line = fgets($handle)) !== false) {
                $entry = json_decode(trim($line), true);
                if (! is_array($entry) || empty($entry['timestamp'])) {
                    continue;
                }

                try {
                    $ts = Carbon::parse((string) $entry['timestamp']);
                } catch (\Throwable) {
                    continue;
                }

                if ($ts->lt($since)) {
                    continue;
                }

                $attemptsTotal++;
                $ip = $entry['ip_address'] ?? null;
                if (is_string($ip) && $ip !== '') {
                    $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
                }
            }
            fclose($handle);
        }

        $suspicious = array_filter($ipCounts, static fn ($count) => $count >= $threshold);
        arsort($suspicious);

        return [
            'status' => empty($suspicious) ? 'ok' : 'warning',
            'failed_logins_24h' => $attemptsTotal,
            'suspicious_ips' => array_slice($suspicious, 0, 10, true),
        ];
    }

    private function durationMs(float $startTime): int
    {
        return (int) round((microtime(true) - $startTime) * 1000);
    }
}
