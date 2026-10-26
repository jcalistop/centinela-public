<?php

namespace App\Console;

use App\Support\Schedule\InProcessArtisanSchedule;
use App\Support\Schedule\ScheduleEventLogging;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Heartbeat horario in-process (SDD-243): vía HTTP el shell/proc_open falla en IIS.
        $heartbeat = InProcessArtisanSchedule::command($schedule, 'schedule:heartbeat')
            ->hourly()
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/schedule-heartbeat.log'));
        ScheduleEventLogging::onFailure(
            $heartbeat,
            'schedule:heartbeat',
            'Error al registrar heartbeat del scheduler'
        );

        // Retención parcial de failed jobs (SDD-178): conserva ventana configurable.
        $pruneFailed = $schedule->command('queue:prune-failed', [
            '--hours' => (int) config('centinela.worker.failed_jobs_retention_hours', 168),
        ])
            ->weekly()
            ->timezone(config('app.timezone'))
            ->appendOutputTo(storage_path('logs/queue-prune-failed.log'));
        ScheduleEventLogging::onFailure(
            $pruneFailed,
            'queue:prune-failed',
            'Error al purgar failed jobs'
        );

        // Deploy auto en lote madrugada (misma ventana que TS-Producción / cron_mail.bat ~00:00).
        // Antes del respaldo diario: el orquestador ejecuta db:backup; el schedule lo omite si es reciente.
        $deployAuto = $schedule->command('centinela:deploy:auto')
            ->dailyAt('00:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->when(fn (): bool => ! config('centinela.schedule.triggered_via_http', false)
                && (bool) config('centinela.deploy.automatic_enabled', false))
            ->appendOutputTo(storage_path('logs/centinela-deploy-auto.log'));
        ScheduleEventLogging::onFailure(
            $deployAuto,
            'centinela:deploy:auto',
            'Error al ejecutar deploy automático'
        );

        // Respaldo automático (lote 00:00). Flag sin valor: `--skip-if-recent=1` rompe Symfony.
        $dbBackup = $schedule->command('db:backup --skip-if-recent', [
            '--email' => config('centinela.backup.email_address') ?: config('services.backup.email_address'),
        ])
            ->dailyAt('00:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/db-backup.log'));
        ScheduleEventLogging::onFailure(
            $dbBackup,
            'db:backup',
            'Error al ejecutar el respaldo de base de datos (mysqldump o permisos)'
        );

        // Limpieza de registros de auditoría antiguos (lote 00:00)
        // Elimina registros mayores a AUDIT_RETENTION_MONTHS (por defecto 6 meses)
        $auditCleanup = $schedule->command('audit:cleanup')
            ->dailyAt('00:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/audit-cleanup.log'));
        ScheduleEventLogging::onFailure(
            $auditCleanup,
            'audit:cleanup',
            'Error al ejecutar la limpieza de auditoría'
        );

        $cleanTemp = $schedule->command('centinela:clean-storage-temp')
            ->dailyAt('00:05')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/storage-temp-cleanup.log'));
        ScheduleEventLogging::onFailure(
            $cleanTemp,
            'centinela:clean-storage-temp',
            'Error al ejecutar la limpieza de storage/temp'
        );

        $pruneLogs = $schedule->command('centinela:prune-schedule-logs')
            ->dailyAt('00:15')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/schedule-logs-prune.log'));
        ScheduleEventLogging::onFailure(
            $pruneLogs,
            'centinela:prune-schedule-logs',
            'Error al ejecutar la retención de logs del scheduler'
        );

        // Auditoría de seguridad sobre intentos fallidos de login
        $securityAudit = $schedule->command('security:audit-check')
            ->dailyAt('00:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/security-audit.log'));
        ScheduleEventLogging::onFailure(
            $securityAudit,
            'security:audit-check',
            'Error al ejecutar auditoría de seguridad'
        );

        // Cierre de sesiones vencidas (hourly): in-process — mismo fallo HTTP/shell que heartbeat (SDD-243).
        $closeSessions = InProcessArtisanSchedule::command($schedule, 'platform:close-expired-auth-sessions')
            ->hourly()
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/platform-auth-sessions-close.log'));
        ScheduleEventLogging::onFailure(
            $closeSessions,
            'platform:close-expired-auth-sessions',
            'Error al cerrar sesiones de autenticación vencidas'
        );

        $supportClose = $schedule->command('centinela:support-close-resolved')
            ->dailyAt('00:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/support-tickets-close-resolved.log'));
        ScheduleEventLogging::onFailure(
            $supportClose,
            'centinela:support-close-resolved',
            'Error al cerrar tickets de soporte resueltos (cierre automático)'
        );

        $cenSync = $schedule->command('cen:sync-establishments')
            ->weeklyOn(1, '02:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->when(fn (): bool => (bool) config('centinela.national_establishments.enabled', false))
            ->appendOutputTo(storage_path('logs/cen-establishments-sync.log'));
        ScheduleEventLogging::onFailure(
            $cenSync,
            'cen:sync-establishments',
            'Error al sincronizar catálogo nacional de establecimientos'
        );

        // Purga de historial de sync CKAN (corridas + incidencias) más antiguo que CEN_SYNC_RUNS_RETENTION_MONTHS
        $cenPrune = $schedule->command('cen:sync-runs:prune')
            ->dailyAt('00:10')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->when(fn (): bool => (bool) config('centinela.national_establishments.enabled', false))
            ->appendOutputTo(storage_path('logs/cen-sync-runs-prune.log'));
        ScheduleEventLogging::onFailure(
            $cenPrune,
            'cen:sync-runs:prune',
            'Error al purgar historial de sincronización de establecimientos'
        );

        $deployPrune = $schedule->command('centinela:deploy:prune-executions')
            ->weeklyOn(0, '03:00')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/centinela-deploy-prune.log'));
        ScheduleEventLogging::onFailure(
            $deployPrune,
            'centinela:deploy:prune-executions',
            'Error al purgar ejecuciones de deploy'
        );

        // Cada 15 min vía monitor HTTP: in-process (SDD-243).
        $deployReconcile = InProcessArtisanSchedule::command($schedule, 'centinela:deploy:reconcile-orphans')
            ->everyFifteenMinutes()
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/centinela-deploy-reconcile-orphans.log'));
        ScheduleEventLogging::onFailure(
            $deployReconcile,
            'centinela:deploy:reconcile-orphans',
            'Error al reconciliar ejecuciones de deploy huérfanas'
        );

        // AG-08: retención de auditoría HTTP de superficie agentes (SDD-248).
        $agentLogPrune = $schedule->command('centinela:agent:prune-request-logs')
            ->dailyAt('00:20')
            ->timezone(config('app.timezone'))
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/agent-request-logs-prune.log'));
        ScheduleEventLogging::onFailure(
            $agentLogPrune,
            'centinela:agent:prune-request-logs',
            'Error al purgar agent_request_logs'
        );
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
