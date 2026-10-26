<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Deploy;

use App\Support\Deploy\DeployOrchestrator;
use Mockery\MockInterface;
use Tests\TestCase;

class DeployOrchestratorLivewirePublishTest extends TestCase
{
    /** @test */
    public function run_livewire_publish_skips_when_disabled(): void
    {
        config(['centinela.deploy.run_livewire_publish_after_composer' => false]);

        $orchestrator = new DeployOrchestrator;
        $method = new \ReflectionMethod(DeployOrchestrator::class, 'runLivewirePublishIfNeeded');
        $method->setAccessible(true);

        $result = $method->invoke($orchestrator, true, ['ok' => true, 'ran' => true, 'summary' => 'composer OK']);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['ran']);
    }

    /** @test */
    public function run_livewire_publish_runs_via_artisan_cli_after_composer_when_code_changed(): void
    {
        config(['centinela.deploy.run_livewire_publish_after_composer' => true]);

        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('runArtisanCli')
                ->once()
                ->with(
                    'livewire:publish',
                    ['--assets' => true, '--no-interaction' => true],
                    300
                )
                ->andReturn(['ok' => true, 'exit_code' => 0, 'output' => '']);
        });

        $method = new \ReflectionMethod(DeployOrchestrator::class, 'runLivewirePublishIfNeeded');
        $method->setAccessible(true);

        $result = $method->invoke($orchestrator, true, ['ok' => true, 'ran' => false, 'summary' => 'Composer omitido']);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['ran']);
        $this->assertStringContainsString('livewire:publish', $result['summary']);
    }
}
