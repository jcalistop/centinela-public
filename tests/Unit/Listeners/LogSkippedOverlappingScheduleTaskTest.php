<?php

namespace Tests\Unit\Listeners;

use App\Listeners\LogSkippedOverlappingScheduleTask;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogSkippedOverlappingScheduleTaskTest extends TestCase
{
    public function test_logs_when_without_overlapping_and_exit_code_null(): void
    {
        Log::spy();

        $schedule = new Schedule;
        $event = $schedule->command('schedule:heartbeat')->withoutOverlapping();
        $this->assertTrue($event->withoutOverlapping);
        $this->assertNull($event->exitCode);

        (new LogSkippedOverlappingScheduleTask)->handle(new ScheduledTaskFinished($event, 0.01));

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'withoutOverlapping')
                    && isset($context['command']);
            });
    }

    public function test_does_not_log_when_exit_code_set(): void
    {
        Log::spy();

        $schedule = new Schedule;
        $event = $schedule->command('schedule:heartbeat')->withoutOverlapping();
        $event->exitCode = 0;

        (new LogSkippedOverlappingScheduleTask)->handle(new ScheduledTaskFinished($event, 0.01));

        Log::shouldNotHaveReceived('info');
    }
}
