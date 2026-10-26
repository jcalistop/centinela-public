<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DeployExecution;
use App\Support\Deploy\DeployStepRecorder;
use Illuminate\Console\Command;

class CentinelaDeployPruneExecutionsCommand extends Command
{
    protected $signature = 'centinela:deploy:prune-executions
                            {--days= : Días de retención (default: config centinela.deploy.executions_retention_days)}';

    protected $description = 'Elimina registros antiguos de deploy_executions y sus artefactos de transcript según retención configurada';

    public function handle(): int
    {
        $days = $this->option('days');
        $retentionDays = is_numeric($days) && (int) $days > 0
            ? (int) $days
            : (int) config('centinela.deploy.executions_retention_days', 90);

        $cutoff = now()->subDays($retentionDays);

        $ids = DeployExecution::query()
            ->whereNotNull('finished_at')
            ->where('finished_at', '<', $cutoff)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $deleted = 0;
        if ($ids !== []) {
            $deleted = DeployExecution::query()->whereIn('id', $ids)->delete();
            $artifactsRemoved = DeployStepRecorder::pruneArtifactDirectories($ids);
            $this->info("Eliminados {$deleted} registros y {$artifactsRemoved} directorios de artefactos con finished_at anterior a {$cutoff->toDateTimeString()} (retención {$retentionDays} días).");
        } else {
            $this->info("Eliminados 0 registros con finished_at anterior a {$cutoff->toDateTimeString()} (retención {$retentionDays} días).");
        }

        return self::SUCCESS;
    }
}
