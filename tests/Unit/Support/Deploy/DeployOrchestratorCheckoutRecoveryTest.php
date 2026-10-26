<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Deploy;

use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeployOrchestratorCheckoutRecoveryTest extends TestCase
{
    /** @test */
    public function ensure_checkout_skips_when_already_aligned(): void
    {
        config([
            'centinela.deploy.branch' => 'main',
            'centinela.deploy.git_status_via_http' => true,
        ]);

        $orchestrator = new class extends DeployOrchestrator
        {
            public function currentCheckoutBranch(): ?string
            {
                return 'main';
            }

            public function tryGitRevParse(string $ref): ?string
            {
                return $ref === 'HEAD' ? 'abc123def456789' : null;
            }
        };

        $result = $orchestrator->ensureCheckoutOnDeployBranch();

        $this->assertFalse($result['attempted']);
        $this->assertTrue($result['recovered']);
    }

    /** @test */
    public function ensure_checkout_recovers_from_detached_head(): void
    {
        config([
            'centinela.deploy.branch' => 'main',
            'centinela.deploy.remote' => 'origin',
            'centinela.deploy.git_status_via_http' => true,
        ]);

        Log::spy();

        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('currentCheckoutBranch')->andReturn(null, 'main');
            $mock->shouldReceive('tryGitRevParse')->andReturn('379a537f97fcf5510d9c2fe889a927e12098abae');
            $mock->shouldReceive('fetchRemoteRefs')->once()->with('origin', 'main');
            $mock->shouldReceive('runGit')
                ->once()
                ->with(['checkout', 'main'], 30)
                ->andReturnUsing(function (): Process {
                    $process = \Mockery::mock(Process::class);
                    $process->shouldReceive('isSuccessful')->andReturn(true);

                    return $process;
                });
        });

        $result = $orchestrator->ensureCheckoutOnDeployBranch();

        $this->assertTrue($result['attempted']);
        $this->assertTrue($result['recovered']);
        $this->assertStringContainsString('recuperado', strtolower($result['message']));
        Log::shouldHaveReceived('info')->with('centinela.deploy.checkout_recovered', \Mockery::type('array'));
    }

    /** @test */
    public function git_checkout_diagnostic_marks_detached_when_abbrev_ref_is_head(): void
    {
        config([
            'centinela.deploy.branch' => 'main',
            'centinela.deploy.git_status_via_http' => true,
        ]);

        $orchestrator = new class extends DeployOrchestrator
        {
            public function currentCheckoutBranch(): ?string
            {
                return null;
            }

            public function tryGitRevParse(string $ref): ?string
            {
                return '379a537f97fcf5510d9c2fe889a927e12098abae';
            }
        };

        $diag = $orchestrator->gitCheckoutDiagnostic();

        $this->assertTrue($diag['detached']);
        $this->assertFalse($diag['aligned']);
        $this->assertSame('379a537', $diag['head_sha_short']);
    }
}
