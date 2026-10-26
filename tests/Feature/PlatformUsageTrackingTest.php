<?php

namespace Tests\Feature;

use App\Http\Middleware\RecordPlatformUsage;
use App\Models\CenHieDependency;
use App\Models\PlatformAreaUsageSample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class PlatformUsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.usage_tracking.enabled' => true,
            'centinela.usage_tracking.throttle_seconds' => 300,
            'cache.default' => 'file',
        ]);

        CenHieDependency::unguarded(fn () => CenHieDependency::firstOrCreate(
            ['id' => 1],
            ['codigo' => 'SS01', 'nombre' => 'Servicio Test', 'tipo_dependencia' => 'SERVICIO']
        ));

        $this->user = User::factory()->create([
            'cen_hie_dependency_id' => 1,
        ]);
    }

    /** @test */
    public function it_records_adp_usage_samples_with_user_and_ss(): void
    {
        $this->actingAs($this->user);
        Session::start();

        $request = Request::create('/admin/adp', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->setId('test-session-id');
        $request->session()->start();

        $this->app->make(RecordPlatformUsage::class)
            ->handle($request, fn () => response('ok'));

        $this->assertDatabaseHas('platform_area_usage_samples', [
            'user_id' => $this->user->getKey(),
            'cen_hie_dependency_id' => 1,
            'area' => 'adp',
        ]);
    }

    /** @test */
    public function it_records_metas_18834_usage_samples(): void
    {
        $this->actingAs($this->user);
        Session::start();

        $request = Request::create('/admin/metas-18834', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->setId('test-session-id-2');
        $request->session()->start();

        $this->app->make(RecordPlatformUsage::class)
            ->handle($request, fn () => response('ok'));

        $this->assertDatabaseHas('platform_area_usage_samples', [
            'user_id' => $this->user->getKey(),
            'area' => 'metas_18834',
        ]);
    }

    /** @test */
    public function it_records_comges_usage_samples_for_indicadores_comges(): void
    {
        $this->actingAs($this->user);
        Session::start();

        $request = Request::create('/admin/indicadores-comges', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->setId('test-session-id-3');
        $request->session()->start();

        $this->app->make(RecordPlatformUsage::class)
            ->handle($request, fn () => response('ok'));

        $this->assertDatabaseHas('platform_area_usage_samples', [
            'user_id' => $this->user->getKey(),
            'area' => 'compromisos',
        ]);
    }

    /** @test */
    public function it_throttles_inserts_per_session_and_area(): void
    {
        $this->actingAs($this->user);
        Session::start();

        $request = Request::create('/admin/adp', 'GET');
        $request->setLaravelSession(app('session')->driver());
        $request->session()->setId('test-session-id-4');
        $request->session()->start();

        $middleware = $this->app->make(RecordPlatformUsage::class);
        $middleware->handle($request, fn () => response('ok'));
        $middleware->handle($request, fn () => response('ok'));

        $this->assertSame(1, PlatformAreaUsageSample::query()->where('area', 'adp')->count());
    }
}
