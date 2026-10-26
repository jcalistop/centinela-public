<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\RunManualDeployJob;
use App\Mail\DeployAdminOpsTerminalMail;
use App\Models\DeployExecution;
use App\Models\User;
use App\Support\Deploy\DeployAdminOpsNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class RunManualDeployJobFailedTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function failed_closes_running_execution_as_failed_with_job_failed_reason(): void
    {
        Mail::fake();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'admin-ops@example.com',
        ]);
        $admin->role()->associate($adminRole)->save();

        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'running',
            'actor_user_id' => $admin->id,
            'branch' => 'main',
            'started_at' => now()->subMinutes(5),
            'summary' => 'En curso',
        ]);

        $job = new RunManualDeployJob($execution->id, $admin->id);
        $job->failed(new \RuntimeException('Simulated worker kill'));

        $execution->refresh();
        $this->assertSame('failed', $execution->status);
        $this->assertNotNull($execution->finished_at);
        $this->assertSame('job_failed', $execution->meta['reason'] ?? null);
        $this->assertSame('failed', $execution->meta[DeployAdminOpsNotifier::META_NOTIFIED_FOR] ?? null);

        Mail::assertQueued(DeployAdminOpsTerminalMail::class, function (DeployAdminOpsTerminalMail $mail) use ($execution): bool {
            return $mail->executionId === $execution->id && $mail->status === 'failed';
        });
    }

    /** @test */
    public function failed_closes_queued_execution_as_failed_precondition(): void
    {
        Mail::fake();

        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'queued',
            'branch' => 'main',
            'summary' => 'En cola',
        ]);

        $job = new RunManualDeployJob($execution->id, 99999);
        $job->failed(new \RuntimeException('timeout'));

        $execution->refresh();
        $this->assertSame('failed_precondition', $execution->status);
        $this->assertNotNull($execution->finished_at);
        $this->assertSame('job_failed', $execution->meta['reason'] ?? null);
    }

    /** @test */
    public function failed_with_missing_execution_does_not_create_row(): void
    {
        Mail::fake();

        $before = DeployExecution::query()->count();
        $job = new RunManualDeployJob(999999, 1);
        $job->failed(new \RuntimeException('gone'));

        $this->assertSame($before, DeployExecution::query()->count());
        Mail::assertNothingQueued();
    }

    /** @test */
    public function failed_does_not_reopen_already_terminal_execution(): void
    {
        Mail::fake();

        $execution = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'branch' => 'main',
            'summary' => 'OK',
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(1),
            'meta' => ['reason' => 'already_done'],
        ]);

        $job = new RunManualDeployJob($execution->id, 1);
        $job->failed(new \RuntimeException('late'));

        $execution->refresh();
        $this->assertSame('succeeded', $execution->status);
        $this->assertSame('already_done', $execution->meta['reason'] ?? null);
        Mail::assertNothingQueued();
    }
}
