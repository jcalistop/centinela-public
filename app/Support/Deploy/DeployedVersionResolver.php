<?php

declare(strict_types=1);

namespace App\Support\Deploy;

use App\Support\Releases\ReleaseSemVer;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DeployedVersionResolver
{
    public function resolveTagFromSha(?string $sha): ?string
    {
        if ($sha === null || trim($sha) === '') {
            return null;
        }

        $ref = trim($sha);

        try {
            $process = $this->runGit([
                'describe',
                '--tags',
                '--exact-match',
                $ref,
            ], 15);

            $tag = trim($process->getOutput());
            if ($tag !== '') {
                return $this->normalizeParsedTag($tag);
            }
        } catch (\Throwable $e) {
            Log::debug('No exact tag for deploy SHA', [
                'sha' => $ref,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $process = $this->runGit([
                'describe',
                '--tags',
                $ref,
            ], 15);

            $parsed = $this->parseDescribeOutput(trim($process->getOutput()));
            if ($parsed !== null) {
                return $parsed;
            }
        } catch (\Throwable $e) {
            Log::debug('git describe --tags failed for deploy SHA', [
                'sha' => $ref,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function parseDescribeOutput(string $output): ?string
    {
        if ($output === '') {
            return null;
        }

        if (preg_match('/^(v\d+\.\d+\.\d+)/i', $output, $matches) !== 1) {
            return null;
        }

        return $this->normalizeParsedTag($matches[1]);
    }

    private function normalizeParsedTag(string $tag): ?string
    {
        $normalized = ReleaseSemVer::normalizeTag($tag);

        return ReleaseSemVer::parse($normalized) !== null ? $normalized : null;
    }

    /**
     * @param  array<int, string>  $args
     */
    protected function runGit(array $args, int $timeoutSeconds): Process
    {
        $gitBinary = (string) config('centinela.deploy.git_binary', 'git');
        $gitBinary = trim($gitBinary) !== '' ? trim($gitBinary) : 'git';

        $process = new Process(array_merge([$gitBinary], $args), base_path());
        $process->setTimeout($timeoutSeconds);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }
}
