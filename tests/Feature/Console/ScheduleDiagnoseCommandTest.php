<?php

namespace Tests\Feature\Console;

use App\Console\Commands\CentinelaPruneScheduleLogsCommand;
use App\Console\Kernel;
use Illuminate\Console\Scheduling\Schedule;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class ScheduleDiagnoseCommandTest extends TestCase
{
    public function test_diagnose_runs_and_reports_sections(): void
    {
        $this->assertContains('schedule-heartbeat.log', CentinelaPruneScheduleLogsCommand::SCHEDULE_LOG_BASENAMES);

        $this->artisan('schedule:diagnose', [
            '--tail' => 5,
            '--skip-probe' => true,
        ])
            ->expectsOutputToContain('schedule:diagnose')
            ->expectsOutputToContain('Mutex / cache lock probe')
            ->expectsOutputToContain('Heartbeat')
            ->expectsOutputToContain('schedule:list')
            ->expectsOutputToContain('Logs dedicados')
            ->assertExitCode(0);
    }

    public function test_heartbeat_has_append_output_in_schedule(): void
    {
        $schedule = new Schedule;
        $kernel = $this->app->make(Kernel::class);
        $method = new ReflectionMethod(Kernel::class, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $heartbeat = null;
        foreach ($schedule->events() as $event) {
            // SDD-243: heartbeat es CallbackEvent in-process (description), no shell command.
            $label = (string) ($event->command ?: $event->description ?? '');
            if (str_contains($label, 'schedule:heartbeat')) {
                $heartbeat = $event;
                break;
            }
        }

        $this->assertNotNull($heartbeat);
        $this->assertStringContainsString('schedule-heartbeat.log', (string) $heartbeat->output);

        $after = new ReflectionProperty($heartbeat, 'afterCallbacks');
        $after->setAccessible(true);
        $this->assertNotEmpty($after->getValue($heartbeat));
    }
}
