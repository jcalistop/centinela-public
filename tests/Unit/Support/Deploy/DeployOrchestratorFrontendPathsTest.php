<?php

namespace Tests\Unit\Support\Deploy;

use App\Support\Deploy\DeployOrchestrator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeployOrchestratorFrontendPathsTest extends TestCase
{
    /** @test */
    public function path_affects_frontend_bundle_matches_ci_patterns(): void
    {
        $orchestrator = new DeployOrchestrator;

        $method = new \ReflectionMethod(DeployOrchestrator::class, 'pathAffectsFrontendBundle');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($orchestrator, 'package.json'));
        $this->assertTrue($method->invoke($orchestrator, 'resources/js/app.js'));
        $this->assertTrue($method->invoke($orchestrator, 'vite.config.js'));
        $this->assertFalse($method->invoke($orchestrator, 'app/Models/User.php'));
        $this->assertFalse($method->invoke($orchestrator, 'database/migrations/foo.php'));
    }

    /** @test */
    public function commit_range_touches_frontend_when_diff_includes_js(): void
    {
        $process = $this->createMock(Process::class);
        $process->method('getOutput')->willReturn("resources/js/app.js\nREADME.md\n");

        /** @var DeployOrchestrator&MockObject $orchestrator */
        $orchestrator = $this->getMockBuilder(DeployOrchestrator::class)
            ->onlyMethods(['runGit'])
            ->getMock();
        $orchestrator->expects($this->once())
            ->method('runGit')
            ->with(['diff', '--name-only', 'aaa', 'bbb'], 30)
            ->willReturn($process);

        $method = new \ReflectionMethod(DeployOrchestrator::class, 'commitRangeTouchesFrontend');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($orchestrator, 'aaa', 'bbb'));
    }
}
