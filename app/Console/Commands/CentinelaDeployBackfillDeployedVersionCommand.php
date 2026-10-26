<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DeployExecution;
use App\Support\Deploy\DeployedVersionResolver;
use App\Support\Deploy\DeployExecutionMode;
use App\Support\Releases\ReleaseSemVer;
use Illuminate\Console\Command;

class CentinelaDeployBackfillDeployedVersionCommand extends Command
{
    protected $signature = 'centinela:deploy:backfill-deployed-version {--dry-run : Solo mostrar qué se actualizaría}';

    protected $description = 'Completa meta.deployed_version en deploys exitosos sin tag exacto en meta';

    public function handle(DeployedVersionResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;

        DeployExecution::query()
            ->whereIn('mode', DeployExecutionMode::deployModes())
            ->where('status', 'succeeded')
            ->orderByDesc('id')
            ->get()
            ->each(function (DeployExecution $execution) use ($resolver, $dryRun, &$updated): void {
                $meta = is_array($execution->meta) ? $execution->meta : [];
                $version = $resolver->resolveTagFromSha($execution->to_sha);
                if ($version === null) {
                    if (empty($meta['deployed_version'])) {
                        $this->warn("Deploy #{$execution->id}: no se pudo resolver versión desde SHA.");
                    }

                    return;
                }

                $stored = isset($meta['deployed_version']) && is_string($meta['deployed_version'])
                    ? ReleaseSemVer::normalizeTag($meta['deployed_version'])
                    : '';

                if ($stored !== '' && $stored === $version) {
                    return;
                }

                $label = $stored !== '' ? "{$stored} → {$version}" : $version;
                $this->line("Deploy #{$execution->id}: {$label}");
                if ($dryRun) {
                    return;
                }

                $meta['deployed_version'] = $version;
                $meta['deployed_at'] = $meta['deployed_at'] ?? $execution->finished_at?->toIso8601String();
                $execution->meta = $meta;
                $execution->save();
                $updated++;
            });

        if ($dryRun) {
            $this->info('Dry-run: no se guardaron cambios.');
        } else {
            $this->info("Actualizados {$updated} registro(s).");
        }

        return self::SUCCESS;
    }
}
