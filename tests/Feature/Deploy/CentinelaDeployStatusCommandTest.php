<?php

namespace Tests\Feature\Deploy;

use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentinelaDeployStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function status_command_prints_operational_snapshot(): void
    {
        $this->mock(DeployOrchestrator::class, function ($mock): void {
            $mock->shouldReceive('operationalStatus')->once()->andReturn([
                'automatic_enabled' => true,
                'schedule_blocked' => false,
                'deploy_branch' => 'main',
                'checkout_branch' => 'main',
                'git_checkout' => [
                    'readable' => true,
                    'git_available' => true,
                    'abbrev_ref' => 'main',
                    'detached' => false,
                    'head_sha_short' => 'abc1234',
                    'deploy_branch' => 'main',
                    'aligned' => true,
                ],
                'branch_aligned' => true,
                'branch_mismatch' => null,
                'local_sha' => 'abc',
                'remote_sha' => 'def',
                'commits_ahead_on_remote' => 0,
                'last_success_sha' => 'abc',
                'has_pending_migrations' => false,
                'would_skip' => true,
                'auto_success_today' => false,
                'last_execution' => null,
            ]);
            $mock->shouldReceive('formatConsoleDiagnosticLines')->once()->andReturn([
                'Rama actual: main @ abc1234.',
            ]);
        });

        $this->artisan('centinela:deploy:status')
            ->expectsOutputToContain('Rama configurada: main')
            ->expectsOutputToContain('Idempotente (skip): sí')
            ->expectsOutputToContain('Diagnóstico:')
            ->expectsOutputToContain('Rama actual: main @ abc1234.')
            ->assertSuccessful();
    }
}
