<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Deploy;

use App\Mail\DeployAdminOpsTerminalMail;
use App\Models\DeployExecution;
use App\Models\User;
use App\Support\Deploy\DeployAdminOpsNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class DeployAdminOpsNotifierTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function notifies_admins_once_per_terminal_status_including_succeeded(): void
    {
        Mail::fake();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'admin-success@example.com',
        ]);
        $admin->role()->associate($adminRole)->save();

        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'actor_user_id' => $admin->id,
            'branch' => 'main',
            'summary' => 'Deploy exitoso.',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $notifier = app(DeployAdminOpsNotifier::class);
        $notifier->notifyIfApplicable($execution);
        $notifier->notifyIfApplicable($execution->fresh() ?? $execution);

        Mail::assertQueued(DeployAdminOpsTerminalMail::class, 1);

        $execution->refresh();
        $this->assertSame('succeeded', $execution->meta[DeployAdminOpsNotifier::META_NOTIFIED_FOR] ?? null);
    }

    /** @test */
    public function mail_failure_does_not_throw_and_leaves_row_closed(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'admin-fail-mail@example.com',
        ]);
        $admin->role()->associate($adminRole)->save();

        $execution = DeployExecution::query()->create([
            'mode' => 'auto',
            'status' => 'failed',
            'branch' => 'main',
            'summary' => 'Boom',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        app(DeployAdminOpsNotifier::class)->notifyIfApplicable($execution);

        $execution->refresh();
        $this->assertSame('failed', $execution->status);
        $this->assertNotNull($execution->finished_at);
        $this->assertArrayNotHasKey(DeployAdminOpsNotifier::META_NOTIFIED_FOR, $execution->meta ?? []);
    }
}
