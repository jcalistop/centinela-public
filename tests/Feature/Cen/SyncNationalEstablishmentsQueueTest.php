<?php

namespace Tests\Feature\Cen;

use App\Http\Controllers\Voyager\CenCenterController;
use App\Jobs\SyncNationalEstablishmentsJob;
use App\Models\CenCenterSyncRun;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;
use TCG\Voyager\Models\Setting;
use Tests\TestCase;

class SyncNationalEstablishmentsQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_national_action_dispatches_job_to_queue(): void
    {
        Queue::fake();
        $this->enableNationalEstablishmentsSync();

        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '24',
            'nombre' => 'Servicio de Salud Del Reloncaví',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $ss->id,
        ]);
        $user->role()->associate($adminRole)->save();

        $this->actingAs($user);

        $response = app(CenCenterController::class)->syncNational(Request::create(
            '/admin/establecimientos/sync-national',
            'POST',
        ));

        $this->assertTrue($response->isRedirect());

        Queue::assertPushed(SyncNationalEstablishmentsJob::class, function (SyncNationalEstablishmentsJob $job) use ($user): bool {
            return $job->triggeredByUserId === $user->id && $job->ssCodigo === '24';
        });
    }

    public function test_sync_national_without_ss_dependency_does_not_dispatch_job(): void
    {
        Queue::fake();
        $this->enableNationalEstablishmentsSync();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id, 'cen_hie_dependency_id' => 99999]);
        $user->role()->associate($adminRole)->save();

        $this->actingAs($user);

        $response = app(CenCenterController::class)->syncNational(Request::create(
            '/admin/establecimientos/sync-national',
            'POST',
        ));

        $this->assertTrue($response->isRedirect());
        Queue::assertNothingPushed();
    }

    public function test_sync_servicio_salud_control_gestion_dispatches_job(): void
    {
        Queue::fake();
        $this->enableNationalEstablishmentsSync();

        $controlGestionRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '24',
            'nombre' => 'Servicio de Salud Del Reloncaví',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $user = User::factory()->create([
            'role_id' => $controlGestionRole->id,
            'cen_hie_dependency_id' => $ss->id,
        ]);
        $user->role()->associate($controlGestionRole)->save();

        $this->actingAs($user);

        $response = app(CenCenterController::class)->syncNational(Request::create(
            '/admin/establecimientos/sync-national',
            'POST',
        ));

        $this->assertTrue($response->isRedirect());

        Queue::assertPushed(SyncNationalEstablishmentsJob::class, function (SyncNationalEstablishmentsJob $job) use ($user): bool {
            return $job->triggeredByUserId === $user->id && $job->ssCodigo === '24';
        });
    }

    public function test_sync_national_full_admin_dispatches_job_without_ss_filter(): void
    {
        Queue::fake();
        $this->enableNationalEstablishmentsSync();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $adminRole->id]);
        $user->role()->associate($adminRole)->save();

        $this->actingAs($user);

        $response = app(CenCenterController::class)->syncNationalFull(Request::create(
            '/admin/establecimientos/sync-national-full',
            'POST',
        ));

        $this->assertTrue($response->isRedirect());

        Queue::assertPushed(SyncNationalEstablishmentsJob::class, function (SyncNationalEstablishmentsJob $job) use ($user): bool {
            return $job->triggeredByUserId === $user->id && $job->ssCodigo === null;
        });
    }

    public function test_sync_national_full_control_gestion_is_forbidden(): void
    {
        Queue::fake();
        $this->enableNationalEstablishmentsSync();

        $controlGestionRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $user = User::factory()->create(['role_id' => $controlGestionRole->id]);
        $user->role()->associate($controlGestionRole)->save();

        $this->actingAs($user);

        try {
            app(CenCenterController::class)->syncNationalFull(Request::create(
                '/admin/establecimientos/sync-national-full',
                'POST',
            ));

            $this->fail('Se esperaba HttpException 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        Queue::assertNothingPushed();
    }

    public function test_sync_national_does_not_dispatch_when_run_already_running(): void
    {
        Queue::fake();
        $this->enableNationalEstablishmentsSync();

        $ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '24',
            'nombre' => 'SS Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        CenCenterSyncRun::query()->create([
            'scope' => 'ss',
            'ss_codigo' => '24',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $ss->id,
        ]);
        $user->role()->associate($adminRole)->save();

        $this->actingAs($user);

        $response = app(CenCenterController::class)->syncNational(Request::create(
            '/admin/establecimientos/sync-national',
            'POST',
        ));

        $this->assertTrue($response->isRedirect());
        Queue::assertNothingPushed();
    }

    public function test_sync_command_dispatches_job_by_default(): void
    {
        Queue::fake();
        $this->enableNationalEstablishmentsSync();

        $this->artisan('cen:sync-establishments', ['--ss' => '24'])
            ->assertSuccessful();

        Queue::assertPushed(SyncNationalEstablishmentsJob::class, function (SyncNationalEstablishmentsJob $job): bool {
            return $job->ssCodigo === '24' && $job->triggeredByUserId === null;
        });
    }

    public function test_sync_command_without_ss_dispatches_full_national_job(): void
    {
        Queue::fake();
        $this->enableNationalEstablishmentsSync();

        $this->artisan('cen:sync-establishments')
            ->assertSuccessful();

        Queue::assertPushed(SyncNationalEstablishmentsJob::class, function (SyncNationalEstablishmentsJob $job): bool {
            return $job->ssCodigo === null && $job->triggeredByUserId === null;
        });
    }

    private function enableNationalEstablishmentsSync(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'module.national_establishments_enabled'],
            [
                'display_name' => 'Sincronización catálogo nacional de establecimientos',
                'value' => 'On',
                'details' => '{}',
                'type' => 'radio_btn',
                'order' => 14,
                'group' => 'Modulos',
            ],
        );
        Config::set('centinela.national_establishments.enabled', true);
    }
}
