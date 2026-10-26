<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Deploy;

use App\Models\DeployExecution;
use App\Models\User;
use App\Support\Configuration\ConfigurationService;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DeployOrchestratorQueuedClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /** @test */
    public function run_manual_with_queued_id_reuses_same_row_when_skip(): void
    {
        $actor = User::factory()->create();
        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'queued',
            'actor_user_id' => $actor->id,
            'branch' => 'main',
            'summary' => 'En cola releases; pendiente de worker.',
            'meta' => [
                'queue' => 'releases',
                'job_id' => '42',
                'dispatched_at' => now()->toIso8601String(),
            ],
        ]);

        /** @var ConfigurationService&MockInterface $configuration */
        $configuration = Mockery::mock(ConfigurationService::class);
        $configuration->shouldReceive('invalidate')->once();
        $configuration->shouldReceive('overrideConfig')->once();
        $this->app->instance(ConfigurationService::class, $configuration);

        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('branchMismatchSummary')->andReturn(null);
            $mock->shouldReceive('ciGateBlockSummary')->andReturn(null);
            $mock->shouldReceive('assessIdempotentDeploy')->andReturn([
                'skip' => true,
                'local_sha' => 'abc123',
                'remote_sha' => 'abc123',
                'last_success_sha' => 'abc123',
                'commits_ahead_on_remote' => 0,
                'has_pending_migrations' => false,
            ]);
            $mock->shouldReceive('tryGitRevParse')->andReturn('abc123');
        });

        Cache::forget(DeployOrchestrator::SCHEDULE_LOCK_KEY);

        $result = $orchestrator->runManual($actor, $execution->id);

        $this->assertTrue($result['ok']);
        $this->assertSame($execution->id, $result['execution_id']);
        $this->assertSame(1, DeployExecution::query()->count());

        $execution->refresh();
        $this->assertSame('skipped', $execution->status);
        $this->assertSame('releases', $execution->meta['queue'] ?? null);
        $this->assertSame('42', $execution->meta['job_id'] ?? null);
    }
}
