<?php

declare(strict_types=1);

namespace App\Support\Deploy;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Evita que cambios locales en rutas dejadas de versionar bloqueen git pull --ff-only.
 */
final class DeployGitPullWorktreeGuard
{
    /**
     * @param  callable(array<int, string>, int): Process  $runGit
     */
    public function __construct(
        private readonly mixed $runGit,
    ) {}

    /**
     * @param  list<string>  $configuredPaths  Rutas generadas (p. ej. public/vendor/livewire/) siempre saneadas antes del pull.
     * @return array{ok: bool, restored: list<string>, summary: string}
     */
    public function prepare(string $remote, string $branch, array $configuredPaths): array
    {
        $deletedUpstream = $this->upstreamDeletedPaths($remote, $branch);
        $normalizedConfigured = $this->normalizePaths($configuredPaths);
        $candidates = array_values(array_unique(array_merge($deletedUpstream, $normalizedConfigured)));

        if ($candidates === []) {
            return [
                'ok' => true,
                'restored' => [],
                'summary' => 'Sin rutas que limpiar antes del pull.',
            ];
        }

        $restored = $this->restorePaths($candidates);

        return [
            'ok' => true,
            'restored' => $restored,
            'summary' => $restored === []
                ? 'Sin cambios locales en rutas candidatas.'
                : 'Restauradas '.count($restored).' ruta(s) locales antes del pull.',
        ];
    }

    /**
     * @return list<string>
     */
    private function upstreamDeletedPaths(string $remote, string $branch): array
    {
        $ref = $remote.'/'.$branch;

        try {
            $process = ($this->runGit)(['diff', '--name-only', '--diff-filter=D', 'HEAD', $ref], 30);
            $lines = preg_split('/\R/', trim($process->getOutput())) ?: [];

            return $this->normalizePaths($lines);
        } catch (\Throwable $e) {
            Log::warning('deploy.git_upstream_deleted_paths_failed', [
                'ref' => $ref,
                'error' => DeployStepRecorder::scrub($e->getMessage()),
            ]);

            return [];
        }
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function restorePaths(array $paths): array
    {
        $restored = [];

        foreach ($paths as $path) {
            if (! $this->shouldAttemptRestore($path)) {
                continue;
            }

            try {
                ($this->runGit)(['restore', '--worktree', '--staged', '--source=HEAD', '--', $path], 30);
                $restored[] = $path;
            } catch (\Throwable $e) {
                Log::warning('deploy.git_restore_failed', [
                    'path' => $path,
                    'error' => DeployStepRecorder::scrub($e->getMessage()),
                ]);
            }
        }

        return $restored;
    }

    private function shouldAttemptRestore(string $path): bool
    {
        $relative = str_replace('\\', '/', $path);

        try {
            $process = ($this->runGit)(['ls-files', '--', $relative], 15);

            return trim($process->getOutput()) !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function normalizePaths(array $paths): array
    {
        $normalized = [];

        foreach ($paths as $path) {
            $path = str_replace('\\', '/', trim((string) $path));
            if ($path !== '') {
                $normalized[] = $path;
            }
        }

        return array_values(array_unique($normalized));
    }
}
