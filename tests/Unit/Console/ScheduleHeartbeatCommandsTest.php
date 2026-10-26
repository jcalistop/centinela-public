<?php

namespace Tests\Unit\Console;

use App\Console\Commands\ScheduleHeartbeat;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use Tests\TestCase;

class ScheduleHeartbeatCommandsTest extends TestCase
{
    /** @test */
    public function heartbeat_lists_current_kernel_commands(): void
    {
        $method = new ReflectionMethod(ScheduleHeartbeat::class, 'getScheduledCommands');
        $method->setAccessible(true);

        /** @var list<string> $commands */
        $commands = $method->invoke(new ScheduleHeartbeat);

        $this->assertContains('queue:prune-failed', $commands);
        $this->assertContains('centinela:prune-schedule-logs', $commands);
        $this->assertContains('security:audit-check', $commands);
        $this->assertContains('centinela:support-close-resolved', $commands);
        $this->assertContains('centinela:deploy:reconcile-orphans', $commands);
        $this->assertNotContains('schedule:daily-report', $commands);
        $this->assertNotContains('queue:process-emails', $commands);
        $this->assertNotContains('queue:flush', $commands);
    }

    /** @test */
    public function heartbeat_writes_file_when_triggered_via_http_flag(): void
    {
        config(['centinela.schedule.triggered_via_http' => true]);

        $exitCode = Artisan::call('schedule:heartbeat');

        $this->assertSame(0, $exitCode);
        $path = storage_path('framework/scheduler-heartbeat.json');
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data);
        $this->assertTrue($data['triggered_via_http'] ?? false);
        $this->assertArrayHasKey('last_run', $data);
    }
}
