<?php

namespace Tests\Feature\Deploy;

use App\Livewire\Admin\DeployDashboard;
use App\Models\User;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class DeployDashboardFeedbackTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function feedback_message_clears_after_refresh_when_expired(): void
    {
        config(['centinela.deploy.automatic_enabled' => true]);
        Bus::fake();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);
        $user->role()->associate($adminRole)->save();

        $this->mock(DeployOrchestrator::class, function ($mock): void {
            $mock->shouldReceive('deployBranch')->andReturn('main');
            $mock->shouldReceive('currentCheckoutBranch')->andReturn('main');
            $mock->shouldReceive('isCheckoutAlignedWithDeployBranch')->andReturn(true);
            $mock->shouldReceive('branchMismatchSummary')->andReturn(null);
            $mock->shouldReceive('ciGateBlockSummary')->andReturn(null);
            $mock->shouldReceive('gitStatusReadableViaHttp')->andReturn(true);
            $mock->shouldReceive('assessIdempotentDeploy')->andReturn([
                'skip' => false,
                'local_sha' => 'a',
                'remote_sha' => 'b',
                'last_success_sha' => 'a',
                'commits_ahead_on_remote' => 1,
                'has_pending_migrations' => false,
            ]);
        });

        Livewire::actingAs($user)
            ->test(DeployDashboard::class)
            ->call('requestManualDeploy')
            ->assertSet('queued', true)
            ->assertSee('Solicitud encolada')
            ->set('feedbackExpiresAt', now()->subSecond()->toIso8601String())
            ->call('refreshDashboard')
            ->assertSet('queued', false)
            ->assertSet('feedbackMessage', null);
    }
}
