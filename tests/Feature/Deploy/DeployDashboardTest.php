<?php

namespace Tests\Feature\Deploy;

use App\Jobs\RunManualDeployJob;
use App\Livewire\Admin\DeployDashboard;
use App\Models\DeployExecution;
use App\Models\GithubReleaseSnapshot;
use App\Models\User;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Mockery\MockInterface;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class DeployDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);
        $user->role()->associate($adminRole)->save();

        return $user;
    }

    /**
     * @param  callable(MockInterface): void|null  $configure
     */
    private function stubDeployOrchestratorForDashboard(?callable $configure = null): void
    {
        $this->mock(DeployOrchestrator::class, function ($mock) use ($configure): void {
            $mock->shouldReceive('deployBranch')->andReturn('main');
            $mock->shouldReceive('currentCheckoutBranch')->andReturn('main');
            $mock->shouldReceive('isCheckoutAlignedWithDeployBranch')->andReturn(true);
            $mock->shouldReceive('branchMismatchSummary')->andReturn(null);
            $mock->shouldReceive('ciGateBlockSummary')->andReturn(null);
            $mock->shouldReceive('gitStatusReadableViaHttp')->andReturn(false);

            if ($configure !== null) {
                $configure($mock);
            }
        });
    }

    /** @test */
    public function manual_deploy_queues_when_git_status_deferred_on_http(): void
    {
        config([
            'centinela.deploy.automatic_enabled' => false,
            'centinela.deploy.git_status_via_http' => false,
        ]);
        Bus::fake();

        $this->partialMock(DeployOrchestrator::class, function ($mock): void {
            $mock->shouldReceive('deployBranch')->andReturn('main');
            $mock->shouldReceive('currentCheckoutBranch')->andReturn(null);
            $mock->shouldReceive('isCheckoutAlignedWithDeployBranch')->andReturn(true);
            $mock->shouldReceive('branchMismatchSummary')->andReturn(null);
            $mock->shouldReceive('ciGateBlockSummary')->andReturn(null);
            $mock->shouldReceive('gitStatusReadableViaHttp')->andReturn(false);
            $mock->shouldReceive('assessIdempotentDeploy')->once()->andReturn([
                'skip' => false,
                'local_sha' => 'abc',
                'remote_sha' => 'def',
                'last_success_sha' => 'abc',
                'commits_ahead_on_remote' => 2,
                'has_pending_migrations' => false,
            ]);
            $mock->shouldNotReceive('runManual');
        });

        Livewire::actingAs($this->adminUser())
            ->test(DeployDashboard::class)
            ->call('requestManualDeploy')
            ->assertSet('queued', true);

        $this->assertSame(1, DeployExecution::query()->where('status', 'queued')->count());
        $execution = DeployExecution::query()->where('status', 'queued')->first();
        $this->assertNotNull($execution);
        $this->assertSame('releases', $execution->meta['queue'] ?? null);
        $this->assertArrayHasKey('dispatched_at', $execution->meta ?? []);

        Bus::assertDispatched(RunManualDeployJob::class, function (RunManualDeployJob $job) use ($execution): bool {
            return $job->executionId === $execution->id
                && $job->queue === 'releases';
        });
    }

    /** @test */
    public function manual_deploy_queues_when_auto_on_and_git_status_deferred_on_http(): void
    {
        config([
            'centinela.deploy.automatic_enabled' => true,
            'centinela.deploy.git_status_via_http' => false,
        ]);
        Bus::fake();

        $this->partialMock(DeployOrchestrator::class, function ($mock): void {
            $mock->shouldReceive('deployBranch')->andReturn('main');
            $mock->shouldReceive('currentCheckoutBranch')->andReturn(null);
            $mock->shouldReceive('isCheckoutAlignedWithDeployBranch')->andReturn(true);
            $mock->shouldReceive('branchMismatchSummary')->andReturn(null);
            $mock->shouldReceive('ciGateBlockSummary')->andReturn(null);
            $mock->shouldReceive('gitStatusReadableViaHttp')->andReturn(false);
            $mock->shouldReceive('assessIdempotentDeploy')->once()->andReturn([
                'skip' => false,
                'local_sha' => null,
                'remote_sha' => null,
                'last_success_sha' => 'abc',
                'commits_ahead_on_remote' => null,
                'has_pending_migrations' => false,
            ]);
            $mock->shouldNotReceive('runManual');
        });

        Livewire::actingAs($this->adminUser())
            ->test(DeployDashboard::class)
            ->call('requestManualDeploy')
            ->assertSet('queued', true)
            ->assertDontSee('Checkout Git no usable');

        Bus::assertDispatched(RunManualDeployJob::class);
    }

    /** @test */
    public function manual_deploy_blocked_when_checkout_branch_mismatches_deploy_branch(): void
    {
        config([
            'centinela.deploy.automatic_enabled' => true,
            'centinela.deploy.git_status_via_http' => true,
        ]);
        Bus::fake();

        $mismatch = 'Checkout local en rama dev pero el deploy está configurado para main.';

        $this->mock(DeployOrchestrator::class, function ($mock) use ($mismatch): void {
            $mock->shouldReceive('deployBranch')->andReturn('main');
            $mock->shouldReceive('currentCheckoutBranch')->andReturn('dev');
            $mock->shouldReceive('isCheckoutAlignedWithDeployBranch')->andReturn(false);
            $mock->shouldReceive('branchMismatchSummary')->andReturn($mismatch);
            $mock->shouldReceive('ciGateBlockSummary')->andReturn(null);
            $mock->shouldReceive('gitStatusReadableViaHttp')->andReturn(true);
            $mock->shouldNotReceive('assessIdempotentDeploy');
            $mock->shouldNotReceive('runManual');
        });

        Livewire::actingAs($this->adminUser())
            ->test(DeployDashboard::class)
            ->call('requestManualDeploy')
            ->assertSet('queued', false)
            ->assertSet('skipped', false)
            ->assertSee($mismatch);

        Bus::assertNotDispatched(RunManualDeployJob::class);
    }

    /** @test */
    public function manual_deploy_skip_shows_message_without_queueing_job(): void
    {
        config(['centinela.deploy.automatic_enabled' => true]);
        Bus::fake();

        $summary = 'Deploy omitido: código al día con remoto y último deploy exitoso; sin migraciones pendientes.';

        $this->stubDeployOrchestratorForDashboard(function ($mock) use ($summary): void {
            $mock->shouldReceive('assessIdempotentDeploy')->once()->andReturn([
                'skip' => true,
                'local_sha' => 'abc',
                'remote_sha' => 'def',
                'last_success_sha' => 'abc',
                'commits_ahead_on_remote' => 0,
                'has_pending_migrations' => false,
            ]);
            $mock->shouldReceive('runManual')->once()->andReturn([
                'ok' => true,
                'execution_id' => 99,
                'summary' => $summary,
            ]);
        });

        Livewire::actingAs($this->adminUser())
            ->test(DeployDashboard::class)
            ->call('requestManualDeploy')
            ->assertSet('skipped', true)
            ->assertSet('queued', false)
            ->assertSee($summary);

        Bus::assertNotDispatched(RunManualDeployJob::class);
    }

    /** @test */
    public function manual_deploy_queues_job_when_not_idempotent(): void
    {
        config(['centinela.deploy.automatic_enabled' => true]);
        Bus::fake();

        $this->stubDeployOrchestratorForDashboard(function ($mock): void {
            $mock->shouldReceive('assessIdempotentDeploy')->once()->andReturn([
                'skip' => false,
                'local_sha' => 'abc',
                'remote_sha' => 'def',
                'last_success_sha' => 'abc',
                'commits_ahead_on_remote' => 2,
                'has_pending_migrations' => false,
            ]);
            $mock->shouldNotReceive('runManual');
        });

        Livewire::actingAs($this->adminUser())
            ->test(DeployDashboard::class)
            ->call('requestManualDeploy')
            ->assertSet('queued', true)
            ->assertSet('skipped', false)
            ->assertSee('Solicitud encolada');

        $execution = DeployExecution::query()->where('status', 'queued')->first();
        $this->assertNotNull($execution);

        Bus::assertDispatched(RunManualDeployJob::class, function (RunManualDeployJob $job) use ($execution): bool {
            return $job->executionId === $execution->id && $job->queue === 'releases';
        });
    }

    /** @test */
    public function manual_deploy_queues_job_when_automatic_disabled(): void
    {
        config(['centinela.deploy.automatic_enabled' => false]);
        Bus::fake();

        $this->stubDeployOrchestratorForDashboard(function ($mock): void {
            $mock->shouldReceive('assessIdempotentDeploy')->once()->andReturn([
                'skip' => false,
                'local_sha' => 'abc',
                'remote_sha' => 'def',
                'last_success_sha' => 'abc',
                'commits_ahead_on_remote' => 2,
                'has_pending_migrations' => false,
            ]);
            $mock->shouldNotReceive('runManual');
        });

        $admin = $this->adminUser();

        Livewire::actingAs($admin)
            ->test(DeployDashboard::class)
            ->call('requestManualDeploy')
            ->assertSet('queued', true)
            ->assertSee('cola releases')
            ->assertSee('#');

        $execution = DeployExecution::query()->where('status', 'queued')->first();
        $this->assertNotNull($execution);
        $this->assertSame($admin->id, (int) $execution->actor_user_id);

        Bus::assertDispatched(RunManualDeployJob::class, function (RunManualDeployJob $job) use ($execution, $admin): bool {
            return $job->queue === 'releases'
                && $job->executionId === $execution->id
                && $job->actorUserId === $admin->id;
        });
    }

    /** @test */
    public function admin_can_open_and_close_execution_detail_modal(): void
    {
        config(['centinela.deploy.automatic_enabled' => true]);

        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'skipped',
            'from_sha' => 'abc123def456',
            'to_sha' => 'abc123def456',
            'branch' => 'main',
            'backup_status' => 'skipped',
            'migrate_status' => 'skipped',
            'rollback_status' => 'skipped',
            'summary' => 'Deploy omitido: prueba.',
            'meta' => ['idempotent' => ['commits_ahead_on_remote' => 0]],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        Livewire::actingAs($this->adminUser())
            ->test(DeployDashboard::class)
            ->call('showExecutionDetail', $execution->id)
            ->assertSet('selectedExecutionId', $execution->id)
            ->assertSee('Deploy #'.$execution->id)
            ->assertSee('Deploy omitido: prueba.')
            ->assertSee('abc123def456')
            ->call('closeExecutionDetail')
            ->assertSet('selectedExecutionId', null);
    }

    /** @test */
    public function manual_mode_dashboard_hides_auto_schedule_badges(): void
    {
        config(['centinela.deploy.automatic_enabled' => false]);
        Cache::put(DeployOrchestrator::SCHEDULE_LOCK_KEY, '1', now()->addHour());

        $this->stubDeployOrchestratorForDashboard();

        Livewire::actingAs($this->adminUser())
            ->test(DeployDashboard::class)
            ->assertSee('Modo: manual (cola releases)')
            ->assertDontSee('Auto:')
            ->assertDontSee('Schedule:');
    }

    /** @test */
    public function auto_mode_dashboard_shows_schedule_block_and_unlock(): void
    {
        config(['centinela.deploy.automatic_enabled' => true]);
        Cache::put(DeployOrchestrator::SCHEDULE_LOCK_KEY, '1', now()->addHour());

        $this->stubDeployOrchestratorForDashboard();

        Livewire::actingAs($this->adminUser())
            ->test(DeployDashboard::class)
            ->assertSee('Auto: ON')
            ->assertSee('Schedule: BLOQUEADO')
            ->assertSee('Desbloquear auto');
    }

    /** @test */
    public function manual_mode_console_hides_auto_deploy_commands(): void
    {
        config(['centinela.deploy.automatic_enabled' => false]);
        $admin = $this->adminUser();

        $this->stubDeployOrchestratorForDashboard();

        Livewire::actingAs($admin)
            ->test(DeployDashboard::class)
            ->assertSee('queue:work --queue=releases')
            ->assertDontSee('centinela:deploy:auto')
            ->assertDontSee('centinela:deploy:unlock');
    }

    /** @test */
    public function dashboard_shows_orchestrator_console_commands(): void
    {
        config(['centinela.deploy.automatic_enabled' => true]);
        $admin = $this->adminUser();

        $this->stubDeployOrchestratorForDashboard();

        Livewire::actingAs($admin)
            ->test(DeployDashboard::class)
            ->assertSee('centinela:deploy:manual')
            ->assertSee('centinela:deploy:status')
            ->assertSee('centinela:deploy:unlock')
            ->assertSee('--email='.$admin->email)
            ->assertSee('php artisan db:backup');
    }

    /** @test */
    public function admin_can_unlock_auto_schedule_from_dashboard(): void
    {
        config(['centinela.deploy.automatic_enabled' => true]);
        Cache::put(DeployOrchestrator::SCHEDULE_LOCK_KEY, '1', now()->addHour());

        Livewire::actingAs($this->adminUser())
            ->test(DeployDashboard::class)
            ->call('unlockAutoSchedule')
            ->assertSee('Bloqueo de deploy automático liberado');

        $this->assertNull(Cache::get(DeployOrchestrator::SCHEDULE_LOCK_KEY));
    }

    /** @test */
    public function dashboard_shows_github_release_panel_and_deploy_versions(): void
    {
        config(['centinela.deploy.automatic_enabled' => false]);

        GithubReleaseSnapshot::upsertFromNormalizedRelease([
            'id' => 451,
            'tag_name' => 'v4.5.1',
            'name' => 'Parche Metas',
            'published_at' => '2026-06-18T19:00:00Z',
            'display_date' => '18/06/2026',
            'body' => 'Notas de prueba',
        ]);

        DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'meta' => ['deployed_version' => 'v4.4.5'],
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);

        $this->stubDeployOrchestratorForDashboard();

        Livewire::actingAs($this->adminUser())
            ->test(DeployDashboard::class)
            ->assertSee('Release GitHub')
            ->assertSee('v4.5.1')
            ->assertSee('PENDIENTE')
            ->assertSee('DEPLOY')
            ->assertSee('v4.4.5');
    }
}
