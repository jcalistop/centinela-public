<?php

namespace Tests\Unit\Support\Deploy;

use App\Models\DeployExecution;
use App\Models\User;
use App\Support\Configuration\ConfigurationService;
use App\Support\Database\DatabaseBackupSkipGuard;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DeployOrchestratorFailedPreconditionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /** @test */
    public function manual_run_records_failed_precondition_on_branch_mismatch(): void
    {
        $actor = User::factory()->create();

        /** @var ConfigurationService&MockInterface $configuration */
        $configuration = Mockery::mock(ConfigurationService::class);
        $configuration->shouldReceive('invalidate')->once();
        $configuration->shouldReceive('overrideConfig')->once();
        $this->app->instance(ConfigurationService::class, $configuration);

        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('branchMismatchSummary')->andReturn('Rama desalineada.');
            $mock->shouldAllowMockingProtectedMethods();
        });

        $result = $orchestrator->runManual($actor);

        $this->assertFalse($result['ok']);
        $this->assertDatabaseHas('deploy_executions', [
            'mode' => 'manual',
            'status' => 'failed_precondition',
            'summary' => 'Rama desalineada.',
        ]);
    }

    /** @test */
    public function manual_run_does_not_block_auto_schedule_on_backup_failure(): void
    {
        $actor = User::factory()->create();

        /** @var ConfigurationService&MockInterface $configuration */
        $configuration = Mockery::mock(ConfigurationService::class);
        $configuration->shouldReceive('invalidate')->once();
        $configuration->shouldReceive('overrideConfig')->once();
        $configuration->shouldReceive('backupNotificationEmail')->andReturn(null);
        $this->app->instance(ConfigurationService::class, $configuration);

        $this->mock(DatabaseBackupSkipGuard::class, function ($mock): void {
            $mock->shouldReceive('shouldSkipScheduledBackup')->andReturn(false);
        });

        Artisan::shouldReceive('call')
            ->once()
            ->with('db:backup', ['--skip-if-recent' => true])
            ->andReturn(1);
        Artisan::shouldReceive('output')->andReturn('backup failed for test');

        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('branchMismatchSummary')->andReturn(null);
            $mock->shouldReceive('assessIdempotentDeploy')->andReturn([
                'skip' => false,
                'local_sha' => 'abc',
                'remote_sha' => 'def',
                'last_success_sha' => 'abc',
                'commits_ahead_on_remote' => 1,
                'has_pending_migrations' => false,
            ]);
            $mock->shouldReceive('tryGitRevParse')->andReturn('abc123');
        });

        Cache::forget(DeployOrchestrator::SCHEDULE_LOCK_KEY);

        $result = $orchestrator->runManual($actor);

        $this->assertFalse($result['ok']);
        $this->assertNull(Cache::get(DeployOrchestrator::SCHEDULE_LOCK_KEY));
        $this->assertDatabaseHas('deploy_executions', [
            'mode' => 'manual',
            'status' => 'failed_precondition',
            'backup_status' => 'failed',
        ]);

        $execution = DeployExecution::query()->latest('id')->first();
        $this->assertNotNull($execution);
        $steps = $execution->meta['steps'] ?? [];
        $this->assertNotEmpty($steps);
        $this->assertSame('backup', $steps[0]['name'] ?? null);
        $this->assertSame('failed', $steps[0]['status'] ?? null);
        $this->assertArrayHasKey('duration_ms', $steps[0]);
    }
}
