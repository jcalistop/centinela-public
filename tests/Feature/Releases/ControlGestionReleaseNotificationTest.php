<?php

declare(strict_types=1);

namespace Tests\Feature\Releases;

use App\Jobs\NotifyControlGestionDeployedRelease;
use App\Livewire\Admin\DeployDashboard;
use App\Mail\ControlGestionReleaseDeployedMail;
use App\Models\CenHieDependency;
use App\Models\DeployExecution;
use App\Models\GithubReleaseSnapshot;
use App\Models\User;
use App\Models\Year;
use App\Services\Releases\ReleaseNotificationService;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class ControlGestionReleaseNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Year::unguarded(fn () => Year::create([
            'ano' => 2026,
            'default' => true,
        ]));
    }

    private function adminUser(): User
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);
        $user->role()->associate($adminRole)->save();

        return $user;
    }

    private function seedDeployedVersion(string $version = 'v4.4.0'): void
    {
        DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'meta' => [
                'deployed_version' => $version,
                'deployed_at' => now()->toIso8601String(),
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    /** @test */
    public function deploy_success_does_not_queue_cg_notification_mail(): void
    {
        Mail::fake();
        $this->seedDeployedVersion();

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    /** @test */
    public function test_mail_action_dispatches_job_for_admins_only(): void
    {
        Bus::fake();
        $this->seedDeployedVersion();

        Cache::put('github.releases:last_good', [
            'releases' => [[
                'id' => 1,
                'tag_name' => 'v4.4.0',
                'name' => 'Release',
                'published_at' => now()->toIso8601String(),
                'display_date' => '01/01/2026',
                'body' => 'Notas',
            ]],
            'synced_at' => now()->toIso8601String(),
        ], now()->addHour());

        config(['centinela.deploy.automatic_enabled' => true]);
        $this->mock(DeployOrchestrator::class, function ($mock): void {
            $mock->shouldReceive('deployBranch')->andReturn('main');
            $mock->shouldReceive('currentCheckoutBranch')->andReturn('main');
            $mock->shouldReceive('isCheckoutAlignedWithDeployBranch')->andReturn(true);
            $mock->shouldReceive('branchMismatchSummary')->andReturn(null);
            $mock->shouldReceive('ciGateBlockSummary')->andReturn(null);
            $mock->shouldReceive('gitStatusReadableViaHttp')->andReturn(false);
        });

        Livewire::actingAs($this->adminUser())
            ->test(DeployDashboard::class)
            ->call('sendTestReleaseMail')
            ->assertSet('feedbackMessage', 'Correo de prueba encolado para administradores.');

        Bus::assertDispatched(NotifyControlGestionDeployedRelease::class, function (NotifyControlGestionDeployedRelease $job): bool {
            return $job->isTest === true;
        });
    }

    /** @test */
    public function official_notification_records_release_notify_execution(): void
    {
        Mail::fake();
        $this->seedDeployedVersion('v4.5.0');

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'nombre' => 'SS habilitado',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
        $cg = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $ss->id,
            'email' => 'cg-test@example.com',
        ]);
        $cg->role()->associate($cgRole)->save();

        GithubReleaseSnapshot::upsertFromNormalizedRelease([
            'id' => 10,
            'tag_name' => 'v4.5.0',
            'name' => 'Cuatro cinco',
            'published_at' => now()->toIso8601String(),
            'display_date' => '28/05/2026',
            'body' => 'Cuerpo oficial',
        ]);

        $admin = $this->adminUser();
        $service = app(ReleaseNotificationService::class);
        $execution = $service->startNotificationExecution($admin, isTest: false);

        $service->executeNotification($execution->id, isTest: false);

        $execution->refresh();
        $this->assertSame('succeeded', $execution->status);
        $this->assertSame('release_notify', $execution->mode);
        $this->assertSame('v4.5.0', $execution->meta['notified_version'] ?? null);
    }

    /** @test */
    public function official_notification_skips_control_gestion_without_habilitado_ss(): void
    {
        Mail::fake();
        $this->seedDeployedVersion('v1.0.0');

        GithubReleaseSnapshot::upsertFromNormalizedRelease([
            'id' => 1,
            'tag_name' => 'v1.0.0',
            'name' => 'Uno',
            'published_at' => now()->toIso8601String(),
            'display_date' => '01/01/2026',
            'body' => 'Body',
        ]);

        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'CG']);
        $disabledSs = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'nombre' => 'SS off',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => false,
        ]));
        $enabledSs = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'nombre' => 'SS on',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $disabledSs->id,
            'email' => 'cg-off@example.com',
        ]);
        $enabledCg = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $enabledSs->id,
            'email' => 'cg-on@example.com',
        ]);
        $enabledCg->role()->associate($cgRole)->save();

        $admin = $this->adminUser();
        $service = app(ReleaseNotificationService::class);
        $execution = $service->startNotificationExecution($admin, isTest: false);
        $service->executeNotification($execution->id, isTest: false);

        Mail::assertQueued(ControlGestionReleaseDeployedMail::class, function (ControlGestionReleaseDeployedMail $mail) use ($enabledCg): bool {
            return $mail->hasTo($enabledCg->email);
        });

        Mail::assertNotQueued(ControlGestionReleaseDeployedMail::class, function (ControlGestionReleaseDeployedMail $mail): bool {
            return $mail->hasTo('cg-off@example.com');
        });
    }
}
