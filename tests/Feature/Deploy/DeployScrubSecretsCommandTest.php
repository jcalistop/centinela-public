<?php

declare(strict_types=1);

namespace Tests\Feature\Deploy;

use App\Models\DeployExecution;
use App\Support\Deploy\DeployStepRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeployScrubSecretsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['centinela.deploy.artifacts_path' => 'logs/deploy-scrub-test']);
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
    public function dry_run_lists_contaminated_rows_without_writing(): void
    {
        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'failed',
            'branch' => 'main',
            'summary' => 'ok clean',
            'meta' => ['note' => 'clean'],
            'finished_at' => now(),
            'started_at' => now(),
        ]);

        // Bypass model saving scrub to simulate historical contamination.
        DB::table('deploy_executions')->where('id', $execution->id)->update([
            'summary' => 'Fallo https://user:ghp_secret@github.com/org/repo.git',
            'meta' => json_encode(['error' => 'token github_pat_ABCDEFG1234567890 leaked'], JSON_THROW_ON_ERROR),
        ]);

        $exit = Artisan::call('centinela:deploy:scrub-secrets', ['--dry-run' => true, '--skip-artifacts' => true]);
        $this->assertSame(0, $exit);

        $raw = DeployExecution::query()->findOrFail($execution->id);
        $this->assertStringContainsString('ghp_secret', (string) $raw->summary);
        $this->assertStringContainsString('github_pat_ABCDEFG', json_encode($raw->meta));
    }

    /** @test */
    public function apply_scrubs_contaminated_row_and_second_pass_is_clean(): void
    {
        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'failed',
            'branch' => 'main',
            'finished_at' => now(),
            'started_at' => now(),
        ]);

        DB::table('deploy_executions')->where('id', $execution->id)->update([
            'summary' => 'https://bot:token123@github.com/x/y.git failed',
            'meta' => json_encode(['msg' => 'github_pat_XYZ1234567890abc'], JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame(0, Artisan::call('centinela:deploy:scrub-secrets', ['--skip-artifacts' => true]));

        $execution->refresh();
        $this->assertStringNotContainsString('token123', (string) $execution->summary);
        $this->assertStringContainsString('https://[REDACTED]@', (string) $execution->summary);
        $this->assertStringNotContainsString('github_pat_XYZ', json_encode($execution->meta));
        $this->assertStringContainsString('[REDACTED_PAT]', json_encode($execution->meta));

        $this->assertSame(0, Artisan::call('centinela:deploy:scrub-secrets', ['--skip-artifacts' => true]));
        $execution->refresh();
        $this->assertStringNotContainsString('token123', (string) $execution->summary);
        $this->assertStringContainsString('[REDACTED_PAT]', json_encode($execution->meta));
    }

    /** @test */
    public function clean_meta_is_unchanged_after_command(): void
    {
        $meta = ['steps' => [['name' => 'git', 'status' => 'finished']], 'queue' => 'releases'];
        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'branch' => 'main',
            'summary' => 'Deploy exitoso.',
            'meta' => $meta,
            'finished_at' => now(),
            'started_at' => now(),
        ]);

        Artisan::call('centinela:deploy:scrub-secrets', ['--skip-artifacts' => true]);
        $execution->refresh();

        $this->assertSame('Deploy exitoso.', $execution->summary);
        $this->assertSame($meta, $execution->meta);
    }

    /** @test */
    public function model_saving_prevents_persisting_secrets(): void
    {
        $execution = DeployExecution::query()->create([
            'mode' => 'auto',
            'status' => 'failed',
            'branch' => 'main',
            'summary' => 'err https://u:p@host/repo',
            'meta' => ['error' => 'github_pat_ABCDEFG1234567890'],
            'finished_at' => now(),
            'started_at' => now(),
        ]);

        $execution->refresh();
        $this->assertStringNotContainsString('u:p@', (string) $execution->summary);
        $this->assertStringNotContainsString('github_pat_ABCDEFG', json_encode($execution->meta));
    }

    /** @test */
    public function scrub_deep_redacts_nested_strings(): void
    {
        $scrubbed = DeployStepRecorder::scrubDeep([
            'git' => ['summary' => 'https://a:b@github.com/x.git', 'nested' => ['pat' => 'github_pat_ABCDEFG1234567890']],
        ]);

        $this->assertSame('https://[REDACTED]@github.com/x.git', $scrubbed['git']['summary']);
        $this->assertSame('[REDACTED_PAT]', $scrubbed['git']['nested']['pat']);
    }
}
