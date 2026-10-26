<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DeployExecution;
use App\Support\Deploy\DeployStepRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CentinelaDeployScrubSecretsCommand extends Command
{
    protected $signature = 'centinela:deploy:scrub-secrets
                            {--dry-run : Solo reportar filas/archivos a scrubbar, sin escribir}
                            {--skip-artifacts : No escanear transcripts bajo storage deploy artifacts}
                            {--logs : También scrubbar storage/logs/laravel-*.log}';

    protected $description = 'Scrub userinfo/PAT de git en deploy_executions, artefactos y (opcional) logs Laravel';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rowsTouched = $this->scrubExecutions($dryRun);
        $artifactsTouched = 0;
        $logsTouched = 0;

        if (! (bool) $this->option('skip-artifacts')) {
            $artifactsTouched = $this->scrubArtifactFiles($dryRun);
        }

        if ((bool) $this->option('logs')) {
            $logsTouched = $this->scrubLaravelLogs($dryRun);
        }

        $verb = $dryRun ? 'Detectados' : 'Actualizados';
        $this->info(sprintf(
            '%s: %d ejecución(es), %d artefacto(s), %d log(s).%s',
            $verb,
            $rowsTouched,
            $artifactsTouched,
            $logsTouched,
            $dryRun ? ' (dry-run: sin escritura)' : ''
        ));

        if ($rowsTouched === 0 && $artifactsTouched === 0 && $logsTouched === 0) {
            $this->line('Sin coincidencias de secretos git (userinfo URL / github_pat_).');
        }

        return self::SUCCESS;
    }

    private function scrubExecutions(bool $dryRun): int
    {
        $touched = 0;

        DeployExecution::query()->orderBy('id')->chunkById(100, function ($rows) use ($dryRun, &$touched): void {
            foreach ($rows as $execution) {
                /** @var DeployExecution $execution */
                $summary = is_string($execution->summary) ? $execution->summary : null;
                $meta = is_array($execution->meta) ? $execution->meta : null;
                $result = DeployStepRecorder::scrubExecutionFields($summary, $meta);

                if (! $result['changed']) {
                    continue;
                }

                $touched++;
                $this->line(sprintf('  execution #%d%s', $execution->id, $dryRun ? ' [dry-run]' : ''));

                if ($dryRun) {
                    continue;
                }

                // Asignar valores ya scrubbed; el hook saving es idempotente.
                $execution->summary = $result['summary'];
                $execution->meta = $result['meta'];
                $execution->save();
            }
        });

        return $touched;
    }

    private function scrubArtifactFiles(bool $dryRun): int
    {
        $base = DeployStepRecorder::artifactsBasePath();
        if (! is_dir($base)) {
            return 0;
        }

        $touched = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            if (! $this->scrubFileInPlace($path, $dryRun)) {
                continue;
            }

            $touched++;
            $this->line(sprintf('  artifact %s%s', $path, $dryRun ? ' [dry-run]' : ''));
        }

        return $touched;
    }

    private function scrubLaravelLogs(bool $dryRun): int
    {
        $dir = storage_path('logs');
        if (! is_dir($dir)) {
            return 0;
        }

        $touched = 0;
        foreach (File::glob($dir.DIRECTORY_SEPARATOR.'laravel-*.log') ?: [] as $path) {
            if (! is_string($path) || ! is_file($path)) {
                continue;
            }

            if (! $this->scrubFileInPlace($path, $dryRun)) {
                continue;
            }

            $touched++;
            $this->line(sprintf('  log %s%s', $path, $dryRun ? ' [dry-run]' : ''));
        }

        return $touched;
    }

    private function scrubFileInPlace(string $path, bool $dryRun): bool
    {
        try {
            $contents = File::get($path);
        } catch (\Throwable $e) {
            Log::warning('centinela.deploy.scrub_secrets.file_read_failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            $this->warn('No se pudo leer: '.$path.' ('.$e->getMessage().')');

            return false;
        }

        if (! DeployStepRecorder::containsSecrets($contents)) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        try {
            File::put($path, DeployStepRecorder::scrub($contents));
        } catch (\Throwable $e) {
            Log::warning('centinela.deploy.scrub_secrets.file_write_failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            $this->warn('No se pudo escribir: '.$path.' ('.$e->getMessage().')');

            return false;
        }

        return true;
    }
}
