<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\RunManualDeployJob;
use App\Models\DeployExecution;
use App\Models\User;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RunManualDeployJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /** @test */
    public function handle_passes_queued_execution_id_to_orchestrator_without_creating_another_row(): void
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
                'dispatched_at' => now()->toIso8601String(),
            ],
        ]);

        $this->mock(DeployOrchestrator::class, function ($mock) use ($actor, $execution): void {
            $mock->shouldReceive('runManual')
                ->once()
                ->with(Mockery::on(fn ($user): bool => $user instanceof User && $user->id === $actor->id), $execution->id)
                ->andReturn([
                    'ok' => true,
                    'execution_id' => $execution->id,
                    'summary' => 'Deploy exitoso.',
                ]);
            $mock->shouldNotReceive('failQueuedExecution');
        });

        (new RunManualDeployJob($execution->id, $actor->id))->handle(app(DeployOrchestrator::class));

        $this->assertSame(1, DeployExecution::query()->count());
    }

    /** @test */
    public function handle_marks_queued_failed_when_actor_missing(): void
    {
        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'queued',
            'actor_user_id' => 999999,
            'branch' => 'main',
            'summary' => 'En cola releases; pendiente de worker.',
            'meta' => ['queue' => 'releases'],
        ]);

        (new RunManualDeployJob($execution->id, 999999))->handle(app(DeployOrchestrator::class));

        $execution->refresh();
        $this->assertSame('failed_precondition', $execution->status);
        $this->assertStringContainsString('Actor no encontrado', (string) $execution->summary);
        $this->assertSame(1, DeployExecution::query()->count());
    }
}
