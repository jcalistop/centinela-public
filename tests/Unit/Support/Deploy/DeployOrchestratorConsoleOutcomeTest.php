<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Deploy;

use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Console\Command;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

class DeployOrchestratorConsoleOutcomeTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @test */
    public function emit_console_outcome_lists_compact_review_footer_when_execution_id_present(): void
    {
        config([
            'centinela.deploy.artifacts_path' => 'logs/deploy',
        ]);

        $lines = [];

        /** @var Command&MockInterface $command */
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('info')->once()->with('Deploy exitoso.');
        $command->shouldReceive('line')->andReturnUsing(function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        (new DeployOrchestrator)->emitConsoleOutcome($command, [
            'ok' => true,
            'execution_id' => 69,
            'summary' => 'Deploy exitoso.',
        ]);

        $this->assertContains('Dónde revisar #69:', $lines);
        $this->assertContains('  → Panel Deploy', $lines);
        $this->assertContains('  → Artefactos: storage/logs/deploy/69/', $lines);
        $this->assertContains('  → Log: centinela.deploy.step', $lines);
        $this->assertNotContains('Trazabilidad (ejecución #69):', $lines);
    }

    /** @test */
    public function emit_console_outcome_skips_traceability_without_execution_id(): void
    {
        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('formatConsoleDiagnosticLines')->andReturn([]);
        });

        $lines = [];

        /** @var Command&MockInterface $command */
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('error')->once()->with('Otro deploy está en progreso.');
        $command->shouldReceive('info')->never();
        $command->shouldReceive('line')->andReturnUsing(function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        $orchestrator->emitConsoleOutcome($command, [
            'ok' => false,
            'execution_id' => null,
            'summary' => 'Otro deploy está en progreso.',
        ]);

        $this->assertSame([''], $lines);
    }
}
