<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Deploy;

use App\Models\DeployExecution;
use App\Models\User;
use App\Support\Deploy\DeployStepRecorder;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class DeployStepRecorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['centinela.deploy.artifacts_path' => 'logs/deploy-test']);
        $base = DeployStepRecorder::artifactsBasePath();
        if (is_dir($base)) {
            File::deleteDirectory($base);
        }
    }

    protected function tearDown(): void
    {
        $base = DeployStepRecorder::artifactsBasePath();
        if (is_dir($base)) {
            File::deleteDirectory($base);
        }

        parent::tearDown();
    }

    /** @test */
    public function scrub_redacts_git_userinfo_and_github_pat(): void
    {
        $raw = 'Cloning https://user:ghp_secret@github.com/org/repo.git with github_pat_ABCDEFG1234567890';

        $scrubbed = DeployStepRecorder::scrub($raw);

        $this->assertStringNotContainsString('ghp_secret', $scrubbed);
        $this->assertStringNotContainsString('github_pat_ABCDEFG1234567890', $scrubbed);
        $this->assertStringContainsString('https://[REDACTED]@', $scrubbed);
        $this->assertStringContainsString('[REDACTED_PAT]', $scrubbed);
    }

    /** @test */
    public function record_persists_steps_logs_and_artifact_on_success(): void
    {
        Log::spy();

        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'running',
            'branch' => 'main',
            'started_at' => now(),
        ]);

        $recorder = new DeployStepRecorder($execution);
        $result = $recorder->record('migrate', function (): array {
            return [
                'ok' => true,
                'output' => "Migrating...\nDone.",
            ];
        });

        $this->assertTrue($result['ok']);
        $execution->refresh();
        $steps = $execution->meta['steps'] ?? [];
        $this->assertCount(1, $steps);
        $this->assertSame('migrate', $steps[0]['name']);
        $this->assertSame('finished', $steps[0]['status']);
        $this->assertIsInt($steps[0]['duration_ms']);
        $this->assertGreaterThanOrEqual(0, $steps[0]['duration_ms']);
        $this->assertIsString($steps[0]['artifact']);
        $this->assertFileExists(storage_path($steps[0]['artifact']));

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context) use ($execution): bool {
                return $message === 'centinela.deploy.step'
                    && ($context['step'] ?? null) === 'migrate'
                    && ($context['status'] ?? null) === 'started'
                    && ($context['execution_id'] ?? null) === $execution->id;
            })
            ->once();

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'centinela.deploy.step'
                    && ($context['step'] ?? null) === 'migrate'
                    && ($context['status'] ?? null) === 'finished'
                    && array_key_exists('duration_ms', $context);
            })
            ->once();
    }

    /** @test */
    public function record_marks_failed_step_and_scrubs_secrets_from_artifact_and_meta_summary(): void
    {
        Log::spy();

        $execution = DeployExecution::query()->create([
            'mode' => 'auto',
            'status' => 'running',
            'branch' => 'main',
            'started_at' => now(),
        ]);

        $secret = 'https://bot:github_pat_SECRETTOKEN123@github.com/x/y.git';
        $recorder = new DeployStepRecorder($execution);
        $result = $recorder->record('git', function () use ($secret): array {
            return [
                'ok' => false,
                'summary' => 'pull failed: '.$secret,
                'output' => $secret."\nfatal: Authentication failed",
            ];
        });

        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString('github_pat_SECRETTOKEN123', (string) ($result['summary'] ?? ''));
        $this->assertStringNotContainsString('github_pat_SECRETTOKEN123', (string) ($result['output'] ?? ''));

        $execution->refresh();
        $step = $execution->meta['steps'][0];
        $this->assertSame('failed', $step['status']);
        $this->assertIsString($step['artifact']);
        $artifactBody = File::get(storage_path($step['artifact']));
        $this->assertStringNotContainsString('github_pat_SECRETTOKEN123', $artifactBody);
        $this->assertStringContainsString('[REDACTED]', $artifactBody);

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'centinela.deploy.step'
                    && ($context['status'] ?? null) === 'failed';
            })
            ->once();
    }

    /** @test */
    public function format_console_duration_uses_ms_or_seconds(): void
    {
        $this->assertSame('6 ms', DeployStepRecorder::formatConsoleDuration(6));
        $this->assertSame('999 ms', DeployStepRecorder::formatConsoleDuration(999));
        $this->assertSame('1.0 s', DeployStepRecorder::formatConsoleDuration(1000));
        $this->assertSame('17.5 s', DeployStepRecorder::formatConsoleDuration(17480));
    }

    /** @test */
    public function record_emits_compact_spanish_console_line_without_started(): void
    {
        $actor = User::factory()->create([
            'email' => 'ops.admin@centinela.test',
        ]);

        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'running',
            'actor_user_id' => $actor->id,
            'branch' => 'main',
            'started_at' => now(),
        ]);

        $lines = [];
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('line')->andReturnUsing(function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        $recorder = new DeployStepRecorder($execution, $command);
        $recorder->record('backup', function (): array {
            return ['ok' => true, 'note' => 'omitido (sin pendientes)'];
        });

        $this->assertSame(
            'Deploy manual · ops.admin@centinela.test · ejecución #'.$execution->id,
            $lines[0]
        );
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('✓', $lines[1]);
        $this->assertStringContainsString('backup', $lines[1]);
        $this->assertStringContainsString('omitido', $lines[1]);
        $this->assertStringNotContainsString('started', implode("\n", $lines));
        $this->assertStringNotContainsString('finished', implode("\n", $lines));
    }

    /** @test */
    public function prune_artifact_directories_removes_execution_folders(): void
    {
        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'branch' => 'main',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $recorder = new DeployStepRecorder($execution);
        $recorder->record('npm', function (): array {
            return ['ok' => true, 'output' => 'built'];
        });

        $dir = DeployStepRecorder::artifactsBasePath().DIRECTORY_SEPARATOR.(string) $execution->id;
        $this->assertDirectoryExists($dir);

        $removed = DeployStepRecorder::pruneArtifactDirectories([$execution->id]);
        $this->assertSame(1, $removed);
        $this->assertDirectoryDoesNotExist($dir);
    }
}
