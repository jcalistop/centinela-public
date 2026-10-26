<?php

namespace Tests\Unit\Support\Deploy;

use App\Models\User;
use App\Services\Github\GithubCiStatusService;
use App\Support\Configuration\ConfigurationService;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DeployOrchestratorCiGateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /** @test */
    public function manual_run_records_failed_precondition_when_ci_gate_blocks(): void
    {
        $actor = User::factory()->create();

        $configuration = Mockery::mock(ConfigurationService::class);
        $configuration->shouldReceive('invalidate')->once();
        $configuration->shouldReceive('overrideConfig')->once();
        $this->app->instance(ConfigurationService::class, $configuration);

        $ci = Mockery::mock(GithubCiStatusService::class);
        $ci->shouldReceive('deployGateBlockReason')
            ->once()
            ->with('manual', null)
            ->andReturn('Gate CI: el último workflow no está en success (failure).');
        $ci->shouldReceive('normalizedRequireCiMode')->andReturn('all');
        $this->app->instance(GithubCiStatusService::class, $ci);

        config([
            'centinela.deploy.require_ci_mode' => 'all',
            'centinela.deploy.require_ci_match_sha' => false,
        ]);

        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('branchMismatchSummary')->andReturn(null);
        });

        $result = $orchestrator->runManual($actor);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Gate CI', $result['summary']);
        $this->assertDatabaseHas('deploy_executions', [
            'mode' => 'manual',
            'status' => 'failed_precondition',
        ]);
    }
}
