<?php

namespace Tests\Feature\Deploy;

use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CentinelaDeployUnlockCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function unlock_command_clears_schedule_lock(): void
    {
        Cache::put(DeployOrchestrator::SCHEDULE_LOCK_KEY, '1', now()->addDay());

        $exit = Artisan::call('centinela:deploy:unlock');

        $this->assertSame(0, $exit);
        $this->assertNull(Cache::get(DeployOrchestrator::SCHEDULE_LOCK_KEY));
    }
}
