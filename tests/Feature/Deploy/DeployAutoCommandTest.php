<?php

namespace Tests\Feature\Deploy;

use App\Models\DeployExecution;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DeployAutoCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function auto_deploy_returns_success_when_disabled(): void
    {
        config(['centinela.deploy.automatic_enabled' => false]);

        $exit = Artisan::call('centinela:deploy:auto');

        $this->assertSame(0, $exit);
        $this->assertSame(0, DeployExecution::query()->count());
    }

    /** @test */
    public function auto_deploy_fails_when_blocked(): void
    {
        config(['centinela.deploy.automatic_enabled' => true]);
        Cache::put(DeployOrchestrator::SCHEDULE_LOCK_KEY, '1', now()->addMinutes(5));

        $exit = Artisan::call('centinela:deploy:auto');

        $this->assertSame(1, $exit);
        $this->assertSame(0, DeployExecution::query()->count());
    }
}
