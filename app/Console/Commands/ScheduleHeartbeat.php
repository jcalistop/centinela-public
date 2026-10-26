<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduleHeartbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedule:heartbeat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Registra un heartbeat del scheduler para monitoreo de salud';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $startTime = microtime(true);

            $heartbeatData = [
                'last_run' => now()->toIso8601String(),
                'status' => 'success',
                'command' => 'schedule:run',
                'duration_ms' => 0,
                'hostname' => gethostname(),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => config('app.env'),
                'timezone' => config('app.timezone'),
                'commands_executed' => $this->getScheduledCommands(),
                'triggered_via_http' => (bool) config('centinela.schedule.triggered_via_http', false),
            ];

            $heartbeatPath = storage_path('framework/scheduler-heartbeat.json');
            $directory = dirname($heartbeatPath);
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new \RuntimeException('No se pudo crear el directorio del heartbeat: '.$directory);
            }

            $jsonData = json_encode($heartbeatData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($jsonData === false) {
                throw new \RuntimeException('No se pudo serializar el heartbeat a JSON');
            }

            $written = file_put_contents($heartbeatPath, $jsonData);
            if ($written === false) {
                throw new \RuntimeException('No se pudo escribir el heartbeat en: '.$heartbeatPath);
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $heartbeatData['duration_ms'] = $duration;

            Log::info('Scheduler heartbeat registrado exitosamente', [
                'duration_ms' => $duration,
                'timestamp' => $heartbeatData['last_run'],
                'hostname' => $heartbeatData['hostname'],
                'triggered_via_http' => $heartbeatData['triggered_via_http'],
            ]);

            $this->info("Heartbeat registrado exitosamente ({$duration}ms)");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Error al registrar scheduler heartbeat', [
                'error' => $e->getMessage(),
                'triggered_via_http' => (bool) config('centinela.schedule.triggered_via_http', false),
            ]);

            $this->error('Error al registrar heartbeat: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Inventario alineado a `Kernel::schedule` (SDD-178 / SDD-243).
     *
     * @return list<string>
     */
    protected function getScheduledCommands(): array
    {
        return [
            'schedule:heartbeat',
            'queue:prune-failed',
            'centinela:deploy:auto',
            'db:backup',
            'audit:cleanup',
            'centinela:clean-storage-temp',
            'centinela:prune-schedule-logs',
            'security:audit-check',
            'platform:close-expired-auth-sessions',
            'centinela:support-close-resolved',
            'cen:sync-establishments',
            'cen:sync-runs:prune',
            'centinela:deploy:prune-executions',
            'centinela:deploy:reconcile-orphans',
        ];
    }
}
