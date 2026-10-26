<?php

namespace App\Console\Commands;

use App\Support\Schedule\ScheduleOutputRedactor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class ScheduleDiagnose extends Command
{
    /**
     * @var string
     */
    protected $signature = 'schedule:diagnose
                            {--tail=20 : Líneas de cola por log dedicado}
                            {--skip-probe : Omitir corrida de prueba de schedule:heartbeat}';

    /**
     * @var string
     */
    protected $description = 'Diagnóstico del scheduler sin consola del servidor (mutex, heartbeat, logs, schedule:list)';

    public function handle(): int
    {
        $tail = max(1, (int) $this->option('tail'));

        $this->info('=== schedule:diagnose (SDD-223) ===');
        $this->line('hostname: '.(string) gethostname());
        $this->line('timezone: '.(string) config('app.timezone'));
        $this->line('env: '.(string) config('app.env'));
        $this->line('triggered_via_http (config actual): '.(config('centinela.schedule.triggered_via_http') ? 'true' : 'false'));
        $this->newLine();

        $this->sectionMutexProbe();
        $this->sectionHeartbeat();
        $this->sectionScheduleList();
        $this->sectionLogTails($tail);

        if (! $this->option('skip-probe')) {
            $this->sectionHeartbeatProbe();
        }

        $this->info('=== fin schedule:diagnose ===');

        return Command::SUCCESS;
    }

    protected function sectionMutexProbe(): void
    {
        $this->info('--- Mutex / cache lock probe ---');
        $key = 'centinela:schedule:diagnose:lock-probe';

        try {
            $lock = Cache::lock($key, 5);
            if ($lock->get()) {
                $this->line('Cache::lock: OK (adquirido y liberado)');
                $lock->release();
            } else {
                $this->warn('Cache::lock: FAIL (no se pudo adquirir)');
            }
        } catch (\Throwable $e) {
            $this->error('Cache::lock: ERROR — '.$e->getMessage());
        }

        $this->newLine();
    }

    protected function sectionHeartbeat(): void
    {
        $this->info('--- Heartbeat ---');
        $path = storage_path('framework/scheduler-heartbeat.json');

        if (! is_file($path)) {
            $this->warn("No existe: {$path}");
            $this->newLine();

            return;
        }

        $raw = file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($data) || empty($data['last_run'])) {
            $this->warn('Heartbeat JSON inválido o sin last_run');
            $this->newLine();

            return;
        }

        try {
            $lastRun = Carbon::parse($data['last_run']);
        } catch (\Throwable) {
            $this->warn('last_run no parseable: '.(string) $data['last_run']);
            $this->newLine();

            return;
        }

        $minutes = (int) now()->diffInMinutes($lastRun);
        $threshold = (int) config(
            'centinela.scheduler.heartbeat_threshold_minutes',
            config('services.scheduler.heartbeat_threshold_minutes', 150)
        );
        $status = $minutes < $threshold ? 'fresh' : 'stale';

        $this->line("path: {$path}");
        $this->line('last_run: '.$lastRun->toIso8601String());
        $this->line("minutes_ago: {$minutes}");
        $this->line("threshold_minutes: {$threshold}");
        $this->line("status: {$status}");
        $this->line('environment (en archivo): '.(string) ($data['environment'] ?? 'n/a'));
        $this->newLine();
    }

    protected function sectionScheduleList(): void
    {
        $this->info('--- schedule:list (resumen) ---');
        Artisan::call('schedule:list');
        $output = ScheduleOutputRedactor::redact(Artisan::output(), 4000);
        foreach (preg_split("/\r\n|\n|\r/", trim($output)) ?: [] as $line) {
            if ($line !== '') {
                $this->line($line);
            }
        }
        $this->newLine();
    }

    protected function sectionLogTails(int $tail): void
    {
        $this->info("--- Logs dedicados (últimas {$tail} líneas) ---");
        $logsDir = storage_path('logs');

        foreach (CentinelaPruneScheduleLogsCommand::SCHEDULE_LOG_BASENAMES as $basename) {
            $path = $logsDir.DIRECTORY_SEPARATOR.$basename;
            $this->line("[{$basename}]");
            if (! is_file($path)) {
                $this->line('  (no existe)');

                continue;
            }

            $lines = $this->tailFile($path, $tail);
            if ($lines === []) {
                $this->line('  (vacío)');

                continue;
            }

            foreach ($lines as $line) {
                $this->line('  '.ScheduleOutputRedactor::redact($line, 500));
            }
        }

        $this->newLine();
    }

    protected function sectionHeartbeatProbe(): void
    {
        $this->info('--- Probe: schedule:heartbeat --no-interaction ---');
        $code = Artisan::call('schedule:heartbeat', ['--no-interaction' => true]);
        $output = ScheduleOutputRedactor::redact(Artisan::output());
        $this->line("exit_code: {$code}");
        foreach (preg_split("/\r\n|\n|\r/", trim($output)) ?: [] as $line) {
            if ($line !== '') {
                $this->line($line);
            }
        }
        $this->newLine();
    }

    /**
     * @return list<string>
     */
    protected function tailFile(string $path, int $lines): array
    {
        $content = @file($path, FILE_IGNORE_NEW_LINES);
        if (! is_array($content) || $content === []) {
            return [];
        }

        return array_values(array_slice($content, -$lines));
    }
}
