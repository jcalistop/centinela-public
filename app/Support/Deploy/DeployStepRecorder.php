<?php

declare(strict_types=1);

namespace App\Support\Deploy;

use App\Models\DeployExecution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Timeline tipada + artefactos scrubbed + logs/consola por etapa (SDD-232).
 */
final class DeployStepRecorder
{
    private const LOG_EVENT = 'centinela.deploy.step';

    private const MAX_ARTIFACT_CHARS = 200_000;

    /** @var list<array<string, mixed>> */
    private array $steps = [];

    public function __construct(
        private readonly DeployExecution $execution,
        private readonly ?Command $console = null,
    ) {
        $this->emitConsoleHeader();
    }

    public static function scrub(string $text): string
    {
        $scrubbed = preg_replace(
            '#https://[^/@\s]+:[^/@\s]+@#i',
            'https://[REDACTED]@',
            $text
        ) ?? $text;

        return preg_replace('/github_pat_[A-Za-z0-9_]+/', '[REDACTED_PAT]', $scrubbed) ?? $scrubbed;
    }

    public static function containsSecrets(string $text): bool
    {
        return self::scrub($text) !== $text;
    }

    /**
     * Scrub recursivo de strings en arrays/objetos escalares (meta JSON).
     */
    public static function scrubDeep(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::scrub($value);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = self::scrubDeep($item);
            }

            return $out;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array{summary: string|null, meta: array<string, mixed>|null, changed: bool}
     */
    public static function scrubExecutionFields(?string $summary, ?array $meta): array
    {
        $scrubbedSummary = is_string($summary) ? self::scrub($summary) : $summary;
        $scrubbedMeta = is_array($meta) ? self::scrubDeep($meta) : $meta;

        $changed = $scrubbedSummary !== $summary
            || json_encode($scrubbedMeta) !== json_encode($meta);

        return [
            'summary' => $scrubbedSummary,
            'meta' => is_array($scrubbedMeta) ? $scrubbedMeta : $meta,
            'changed' => $changed,
        ];
    }

    /**
     * @param  callable(): array{ok: bool, output?: string|null, note?: string|null}  $callback
     * @return array{ok: bool, output?: string|null, note?: string|null}
     */
    public function record(string $name, callable $callback): array
    {
        $startedAt = now();
        $startedIso = $startedAt->toIso8601String();

        Log::info(self::LOG_EVENT, [
            'execution_id' => $this->execution->id,
            'step' => $name,
            'status' => 'started',
        ]);

        try {
            $result = $callback();
        } catch (\Throwable $e) {
            $finishedAt = now();
            $durationMs = (int) max(0, $startedAt->diffInMilliseconds($finishedAt));
            $output = self::scrub($e->getMessage());
            $artifact = $this->writeArtifact($name, $output !== '' ? $output : null);

            $this->appendStep([
                'name' => $name,
                'status' => 'failed',
                'started_at' => $startedIso,
                'finished_at' => $finishedAt->toIso8601String(),
                'duration_ms' => $durationMs,
                'artifact' => $artifact,
                'note' => $artifact === null && $output === '' ? 'sin salida' : null,
            ]);
            $this->persistSteps();

            Log::error(self::LOG_EVENT, [
                'execution_id' => $this->execution->id,
                'step' => $name,
                'status' => 'failed',
                'duration_ms' => $durationMs,
                'artifact' => $artifact,
            ]);
            $this->emitConsoleStepLine($name, 'failed', $durationMs, null);

            throw $e;
        }

        $ok = (bool) ($result['ok'] ?? false);
        $rawOutput = isset($result['output']) && is_string($result['output']) ? $result['output'] : null;
        $note = isset($result['note']) && is_string($result['note']) ? $result['note'] : null;
        if (isset($result['summary']) && is_string($result['summary'])) {
            $result['summary'] = self::scrub($result['summary']);
        }
        $scrubbedOutput = is_string($rawOutput) ? self::scrub($rawOutput) : null;
        if (is_string($scrubbedOutput) && strlen($scrubbedOutput) > self::MAX_ARTIFACT_CHARS) {
            $scrubbedOutput = substr($scrubbedOutput, 0, self::MAX_ARTIFACT_CHARS)."\n…[truncated]";
        }

        $finishedAt = now();
        $durationMs = (int) max(0, $startedAt->diffInMilliseconds($finishedAt));
        $status = $ok ? 'finished' : 'failed';
        $artifact = $this->writeArtifact($name, $scrubbedOutput);

        if ($artifact === null && ($scrubbedOutput === null || trim($scrubbedOutput) === '')) {
            $note = $note ?? 'artifact null (salida vacía o omitida)';
        }

        $this->appendStep([
            'name' => $name,
            'status' => $status,
            'started_at' => $startedIso,
            'finished_at' => $finishedAt->toIso8601String(),
            'duration_ms' => $durationMs,
            'artifact' => $artifact,
            'note' => $note,
        ]);
        $this->persistSteps();

        $level = $ok ? 'info' : 'error';
        Log::{$level}(self::LOG_EVENT, [
            'execution_id' => $this->execution->id,
            'step' => $name,
            'status' => $status,
            'duration_ms' => $durationMs,
            'artifact' => $artifact,
        ]);
        $this->emitConsoleStepLine($name, $status, $durationMs, $note);

        if (is_string($scrubbedOutput) && isset($result['output'])) {
            $result['output'] = $scrubbedOutput;
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    public static function artifactsBasePath(): string
    {
        $configured = (string) config('centinela.deploy.artifacts_path', 'logs/deploy');
        $configured = trim(str_replace(['\\', '..'], ['/', ''], $configured), '/');

        return storage_path($configured !== '' ? $configured : 'logs/deploy');
    }

    public static function pruneArtifactDirectories(array $executionIds): int
    {
        $base = self::artifactsBasePath();
        if (! is_dir($base)) {
            return 0;
        }

        $removed = 0;
        foreach ($executionIds as $id) {
            $id = (int) $id;
            if ($id < 1) {
                continue;
            }
            $dir = $base.DIRECTORY_SEPARATOR.(string) $id;
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function appendStep(array $step): void
    {
        $this->steps[] = $step;
    }

    private function persistSteps(): void
    {
        $meta = is_array($this->execution->meta) ? $this->execution->meta : [];
        $meta['steps'] = $this->steps;
        $this->execution->meta = $meta;
        $this->execution->save();
    }

    private function writeArtifact(string $name, ?string $content): ?string
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        try {
            $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $name) ?: 'step';
            $dir = self::artifactsBasePath().DIRECTORY_SEPARATOR.(string) $this->execution->id;
            if (! is_dir($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            $absolute = $dir.DIRECTORY_SEPARATOR.$safeName.'.txt';
            File::put($absolute, $content);

            $relativeBase = trim(str_replace('\\', '/', (string) config('centinela.deploy.artifacts_path', 'logs/deploy')), '/');

            return $relativeBase.'/'.$this->execution->id.'/'.$safeName.'.txt';
        } catch (\Throwable $e) {
            Log::warning(self::LOG_EVENT.'.artifact_write_failed', [
                'execution_id' => $this->execution->id,
                'step' => $name,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function emitConsoleHeader(): void
    {
        if (! $this->console instanceof Command) {
            return;
        }

        $mode = (string) ($this->execution->mode ?? 'manual');
        $label = $mode === 'auto' ? 'Deploy auto' : 'Deploy manual';
        $parts = [$label];

        $actorEmail = $this->execution->actor?->email;
        if (is_string($actorEmail) && $actorEmail !== '') {
            $parts[] = $actorEmail;
        } elseif ($this->execution->actor_user_id !== null) {
            $parts[] = 'actor #'.$this->execution->actor_user_id;
        }

        $parts[] = 'ejecución #'.$this->execution->id;
        $this->console->line(implode(' · ', $parts));
    }

    private function emitConsoleStepLine(string $name, string $status, int $durationMs, ?string $note): void
    {
        if (! $this->console instanceof Command) {
            return;
        }

        $failed = $status === 'failed';
        $mark = $failed ? '✗' : '✓';
        $label = $failed ? 'falló' : $this->consoleLabelFromNote($note);
        $duration = self::formatConsoleDuration($durationMs);
        $style = $failed ? 'error' : 'info';

        $this->console->line(sprintf(
            '  <%s>%s</%s> %-20s %-10s %8s',
            $style,
            $mark,
            $style,
            $name,
            $label,
            $duration
        ));
    }

    private function consoleLabelFromNote(?string $note): string
    {
        if ($note === null || trim($note) === '') {
            return 'ok';
        }

        $lower = mb_strtolower($note);
        if (str_contains($lower, 'omit') || str_contains($lower, 'sin pendientes') || str_contains($lower, 'skip')) {
            return 'omitido';
        }

        return 'ok';
    }

    public static function formatConsoleDuration(int $durationMs): string
    {
        if ($durationMs < 1000) {
            return $durationMs.' ms';
        }

        return number_format($durationMs / 1000, 1, '.', '').' s';
    }
}
