<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retención de logs dedicados del scheduler (appendOutputTo).
 * Los .log activos se rotan por tamaño (mtime siempre es reciente al correr el schedule).
 */
class CentinelaPruneScheduleLogsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'centinela:prune-schedule-logs
                            {--days= : Días de retención de rotados (default: config)}
                            {--max-bytes= : Rotar el log activo si supera este tamaño (default: config)}
                            {--dry-run : Simular sin rotar ni borrar}';

    /**
     * @var string
     */
    protected $description = 'Rota por tamaño y purga rotados antiguos de logs dedicados del scheduler';

    /**
     * Basenames whitelist (Kernel appendOutputTo + este comando). No toca laravel.log.
     *
     * @var list<string>
     */
    public const SCHEDULE_LOG_BASENAMES = [
        'centinela-deploy-auto.log',
        'db-backup.log',
        'audit-cleanup.log',
        'storage-temp-cleanup.log',
        'security-audit.log',
        'platform-auth-sessions-close.log',
        'support-tickets-close-resolved.log',
        'cen-establishments-sync.log',
        'cen-sync-runs-prune.log',
        'centinela-deploy-prune.log',
        'schedule-logs-prune.log',
        'schedule-heartbeat.log',
        'queue-prune-failed.log',
        'agent-request-logs-prune.log',
    ];

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $retentionDays = $daysOption !== null && $daysOption !== ''
            ? (int) $daysOption
            : (int) config('centinela.schedule_logs.retention_days', 14);

        $maxBytesOption = $this->option('max-bytes');
        $maxBytes = $maxBytesOption !== null && $maxBytesOption !== ''
            ? (int) $maxBytesOption
            : (int) config('centinela.schedule_logs.rotate_max_bytes', 5_242_880);

        if ($retentionDays < 1) {
            $this->error('El valor de --days debe ser un entero mayor o igual a 1.');

            return Command::FAILURE;
        }

        if ($maxBytes < 1) {
            $this->error('El valor de --max-bytes debe ser un entero mayor o igual a 1.');

            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $logsDir = storage_path('logs');
        $cutoff = Carbon::now()->subDays($retentionDays);

        $this->info('Prune de logs del scheduler');
        $this->line("  Retención rotados: {$retentionDays} días (antes de {$cutoff->toDateTimeString()})");
        $this->line('  Rotar activo si tamaño ≥ '.number_format($maxBytes).' bytes');
        $this->line('  Modo: '.($dryRun ? 'simulación (--dry-run)' : 'aplicar'));
        $this->newLine();

        if (! is_dir($logsDir)) {
            $this->info('No existe storage/logs; nada que hacer.');

            return Command::SUCCESS;
        }

        $rotated = 0;
        $deleted = 0;
        $bytesFreed = 0;

        foreach (self::SCHEDULE_LOG_BASENAMES as $basename) {
            $activePath = $logsDir.DIRECTORY_SEPARATOR.$basename;
            if (is_file($activePath) && filesize($activePath) >= $maxBytes) {
                $archiveName = pathinfo($basename, PATHINFO_FILENAME).'-'.now()->format('YmdHis').'.log';
                $archivePath = $logsDir.DIRECTORY_SEPARATOR.$archiveName;

                if ($dryRun) {
                    $this->line("[dry-run] rotaría {$basename} → {$archiveName} (".number_format((int) filesize($activePath)).' bytes)');
                } else {
                    if (! rename($activePath, $archivePath)) {
                        $this->warn("No se pudo rotar: {$activePath}");
                        Log::warning('centinela:prune-schedule-logs no pudo rotar', ['path' => $activePath]);

                        continue;
                    }
                    file_put_contents($activePath, '');
                    $this->line("Rotado: {$basename} → {$archiveName}");
                }
                $rotated++;
            }

            $stem = pathinfo($basename, PATHINFO_FILENAME);
            $pattern = $logsDir.DIRECTORY_SEPARATOR.$stem.'-*.log';
            foreach (glob($pattern) ?: [] as $archivePath) {
                if (! is_file($archivePath)) {
                    continue;
                }

                $mtime = Carbon::createFromTimestamp((int) filemtime($archivePath));
                if ($mtime->greaterThanOrEqualTo($cutoff)) {
                    continue;
                }

                $size = (int) filesize($archivePath);
                if ($dryRun) {
                    $this->line('[dry-run] eliminaría rotado: '.basename($archivePath).' ('.number_format($size).' bytes)');
                } elseif (! unlink($archivePath)) {
                    $this->warn("No se pudo eliminar: {$archivePath}");
                    Log::warning('centinela:prune-schedule-logs no pudo eliminar', ['path' => $archivePath]);

                    continue;
                }

                $deleted++;
                $bytesFreed += $size;
            }
        }

        $this->info("Rotaciones: {$rotated}");
        $this->info("Rotados eliminados: {$deleted}");
        $this->line('Espacio liberado (aprox.): '.number_format($bytesFreed).' bytes');

        Log::info('centinela:prune-schedule-logs ejecutado', [
            'rotated' => $rotated,
            'deleted' => $deleted,
            'bytes_freed' => $bytesFreed,
            'retention_days' => $retentionDays,
            'max_bytes' => $maxBytes,
            'dry_run' => $dryRun,
        ]);

        return Command::SUCCESS;
    }
}
