<?php

namespace App\Console\Commands;

use App\Models\CenCenterSyncRun;
use App\Models\CenCenterSyncRunIssue;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PruneCenCenterSyncRunsCommand extends Command
{
    protected $signature = 'cen:sync-runs:prune
                            {--months= : Meses de retención (por defecto: config centinela)}
                            {--dry-run : Simular eliminación sin ejecutarla}';

    protected $description = 'Elimina corridas de sincronización CKAN e incidencias asociadas más antiguas que el período de retención';

    public function handle(): int
    {
        if (! Schema::hasTable('cen_center_sync_runs')) {
            $this->warn('La tabla cen_center_sync_runs no existe; no hay nada que purgar.');

            return self::SUCCESS;
        }

        $monthsToKeep = (int) ($this->option('months')
            ?: config('centinela.national_establishments.sync_runs_retention_months', 6));

        if ($monthsToKeep < 1) {
            $this->error('El período de retención debe ser al menos 1 mes.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subMonths($monthsToKeep);

        $this->info('Purga de historial de sincronización nacional');
        $this->line("  - Retención: {$monthsToKeep} mes(es)");
        $this->line('  - Fecha límite (excluye corridas con started_at/created_at >=): '.$cutoff->format('Y-m-d H:i:s'));
        $this->line('  - Modo: '.($dryRun ? 'SIMULACIÓN' : 'EJECUCIÓN REAL'));
        $this->newLine();

        $runsQuery = CenCenterSyncRun::query()
            ->where('status', '!=', 'running')
            ->where(function ($query) use ($cutoff): void {
                $query->where('started_at', '<', $cutoff)
                    ->orWhere(function ($inner) use ($cutoff): void {
                        $inner->whereNull('started_at')
                            ->where('created_at', '<', $cutoff);
                    });
            });

        $runsToDelete = (clone $runsQuery)->count();

        if ($runsToDelete === 0) {
            $this->info('No hay corridas antiguas para eliminar.');

            Log::info('[cen.sync-runs:prune] completed', [
                'runs_deleted' => 0,
                'issues_deleted' => 0,
                'cutoff' => $cutoff->toIso8601String(),
                'retention_months' => $monthsToKeep,
                'dry_run' => $dryRun,
            ]);

            return self::SUCCESS;
        }

        $issuesToDelete = Schema::hasTable('cen_center_sync_run_issues')
            ? CenCenterSyncRunIssue::query()
                ->whereIn('cen_center_sync_run_id', (clone $runsQuery)->select('id'))
                ->count()
            : 0;

        $this->warn("Corridas a eliminar: {$runsToDelete}");
        $this->line("  - Incidencias asociadas (cascade): {$issuesToDelete}");

        if ($dryRun) {
            $this->newLine();
            $this->info('[SIMULACIÓN] No se eliminó ningún registro (ejecute sin --dry-run).');

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && ! $this->confirm('¿Continuar con la eliminación?', true)) {
            $this->warn('Operación cancelada.');

            return self::SUCCESS;
        }

        $deletedRuns = 0;
        $batchSize = 50;

        $this->info("\nEliminando corridas en lotes de {$batchSize}...");
        $progressBar = $this->output->createProgressBar($runsToDelete);

        (clone $runsQuery)
            ->orderBy('id')
            ->chunkById($batchSize, function ($runs) use (&$deletedRuns, $progressBar): void {
                $ids = $runs->pluck('id')->all();
                $deleted = CenCenterSyncRun::query()->whereIn('id', $ids)->delete();
                $deletedRuns += $deleted;
                $progressBar->advance($deleted);
                usleep(50_000);
            });

        $progressBar->finish();
        $this->newLine(2);

        Log::info('[cen.sync-runs:prune] completed', [
            'runs_deleted' => $deletedRuns,
            'issues_deleted' => $issuesToDelete,
            'cutoff' => $cutoff->toIso8601String(),
            'retention_months' => $monthsToKeep,
            'dry_run' => false,
        ]);

        $this->info('✓ Purga completada');
        $this->line("  - Corridas eliminadas: {$deletedRuns}");
        $this->line("  - Incidencias eliminadas (aprox.): {$issuesToDelete}");

        return self::SUCCESS;
    }
}
