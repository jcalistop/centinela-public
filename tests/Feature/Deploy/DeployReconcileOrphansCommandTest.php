<?php

declare(strict_types=1);

namespace Tests\Feature\Deploy;

use App\Mail\DeployAdminOpsTerminalMail;
use App\Models\DeployExecution;
use App\Models\User;
use App\Support\Deploy\DeployAdminOpsNotifier;
use App\Support\Deploy\DeployOrphanReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class DeployReconcileOrphansCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function reconcile_closes_orphan_older_than_ttl_and_mails_admins(): void
    {
        Mail::fake();
        config(['centinela.deploy.orphan_ttl_seconds' => 1800]);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'ops-admin@example.com',
        ]);
        $admin->role()->associate($adminRole)->save();

        $orphan = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'running',
            'actor_user_id' => $admin->id,
            'branch' => 'main',
            'started_at' => now()->subSeconds(2000),
            'summary' => 'Stuck',
        ]);

        $fresh = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'running',
            'actor_user_id' => $admin->id,
            'branch' => 'main',
            'started_at' => now()->subSeconds(60),
            'summary' => 'Still fresh',
        ]);

        $exit = Artisan::call('centinela:deploy:reconcile-orphans');
        $this->assertSame(0, $exit);

        $orphan->refresh();
        $fresh->refresh();

        $this->assertSame('failed', $orphan->status);
        $this->assertNotNull($orphan->finished_at);
        $this->assertSame('orphan_timeout', $orphan->meta['reason'] ?? null);
        $this->assertSame('failed', $orphan->meta[DeployAdminOpsNotifier::META_NOTIFIED_FOR] ?? null);

        $this->assertSame('running', $fresh->status);
        $this->assertNull($fresh->finished_at);

        Mail::assertQueued(DeployAdminOpsTerminalMail::class, 1);
    }

    /** @test */
    public function reconcile_second_pass_is_idempotent_without_extra_mail(): void
    {
        Mail::fake();
        config(['centinela.deploy.orphan_ttl_seconds' => 60]);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'ops-admin2@example.com',
        ]);
        $admin->role()->associate($adminRole)->save();

        $queued = DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'queued',
            'actor_user_id' => $admin->id,
            'branch' => 'main',
            'summary' => 'En cola huérfana',
        ]);
        $queued->created_at = now()->subSeconds(120);
        $queued->updated_at = now()->subSeconds(120);
        $queued->save();

        /** @var DeployOrphanReconciler $reconciler */
        $reconciler = app(DeployOrphanReconciler::class);
        $this->assertSame(1, $reconciler->reconcile());
        $this->assertSame(0, $reconciler->reconcile());

        Mail::assertQueued(DeployAdminOpsTerminalMail::class, 1);
    }
}
