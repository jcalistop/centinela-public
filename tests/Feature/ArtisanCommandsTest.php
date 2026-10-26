<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ArtisanCommandsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_runs_audit_cleanup_command()
    {
        $exitCode = Artisan::call('audit:cleanup', ['--no-interaction' => true]);
        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_runs_process_queue_command()
    {
        $exitCode = Artisan::call('queue:process-emails');
        $this->assertEquals(0, $exitCode);
    }

    /** @test */
    public function it_runs_schedule_heartbeat_command()
    {
        $exitCode = Artisan::call('schedule:heartbeat');
        $this->assertEquals(0, $exitCode);
        $this->assertFileExists(storage_path('framework/scheduler-heartbeat.json'));
    }
}
