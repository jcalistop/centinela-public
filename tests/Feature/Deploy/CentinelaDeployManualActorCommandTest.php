<?php

declare(strict_types=1);

namespace Tests\Feature\Deploy;

use App\Models\User;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery\MockInterface;
use Tests\TestCase;

class CentinelaDeployManualActorCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function resolves_actor_by_email_and_passes_to_orchestrator(): void
    {
        $actor = User::factory()->create([
            'email' => 'ops.admin@centinela.test',
        ]);

        $this->mockOrchestratorExpectingActor($actor);

        $exit = $this->artisan('centinela:deploy:manual', [
            '--email' => 'ops.admin@centinela.test',
        ])->run();

        $this->assertSame(0, $exit);
    }

    /** @test */
    public function resolves_actor_by_user_id_for_compat(): void
    {
        $actor = User::factory()->create();

        $this->mockOrchestratorExpectingActor($actor);

        $exit = $this->artisan('centinela:deploy:manual', [
            '--user_id' => (string) $actor->id,
        ])->run();

        $this->assertSame(0, $exit);
    }

    /** @test */
    public function rejects_email_and_user_id_together(): void
    {
        $actor = User::factory()->create([
            'email' => 'ops.admin@centinela.test',
        ]);

        $this->mock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('setConsoleCommand')->never();
            $mock->shouldReceive('runManual')->never();
            $mock->shouldReceive('emitConsoleOutcome')->never();
        });

        $this->artisan('centinela:deploy:manual', [
            '--email' => $actor->email,
            '--user_id' => (string) $actor->id,
        ])
            ->expectsOutput('Opciones mutuamente excluyentes: usa --email o --user_id, no ambos.')
            ->assertFailed();
    }

    /** @test */
    public function rejects_unknown_email(): void
    {
        $this->mock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('setConsoleCommand')->never();
            $mock->shouldReceive('runManual')->never();
            $mock->shouldReceive('emitConsoleOutcome')->never();
        });

        $this->artisan('centinela:deploy:manual', [
            '--email' => 'nobody@centinela.test',
        ])
            ->expectsOutput('Actor no encontrado para email: nobody@centinela.test')
            ->assertFailed();
    }

    /** @test */
    public function rejects_when_no_flags_and_no_auth(): void
    {
        Auth::logout();

        $this->mock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('setConsoleCommand')->never();
            $mock->shouldReceive('runManual')->never();
            $mock->shouldReceive('emitConsoleOutcome')->never();
        });

        $this->artisan('centinela:deploy:manual')
            ->expectsOutput('Actor requerido (--email, --user_id o auth).')
            ->assertFailed();
    }

    /** @test */
    public function uses_authenticated_user_when_no_flags(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $this->mockOrchestratorExpectingActor($actor);

        $exit = $this->artisan('centinela:deploy:manual')->run();

        $this->assertSame(0, $exit);
    }

    private function mockOrchestratorExpectingActor(User $actor): void
    {
        $this->mock(DeployOrchestrator::class, function (MockInterface $mock) use ($actor): void {
            $mock->shouldReceive('setConsoleCommand')->once()->andReturnSelf();
            $mock->shouldReceive('runManual')
                ->once()
                ->withArgs(function (User $resolved) use ($actor): bool {
                    return $resolved->is($actor);
                })
                ->andReturn([
                    'ok' => true,
                    'execution_id' => 1,
                    'summary' => 'Deploy exitoso.',
                ]);
            $mock->shouldReceive('emitConsoleOutcome')->once();
        });
    }
}
