<?php

namespace Tests\Unit\Support;

use App\Console\Kernel;
use App\Support\Schedule\InProcessArtisanSchedule;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use ReflectionMethod;
use Tests\TestCase;

class InProcessArtisanScheduleTest extends TestCase
{
    /** @test */
    public function registers_callback_event_named_after_command(): void
    {
        $schedule = new Schedule;
        $event = InProcessArtisanSchedule::command($schedule, 'schedule:heartbeat');

        $this->assertInstanceOf(CallbackEvent::class, $event);
        $this->assertSame('schedule:heartbeat', $event->description);
    }

    /** @test */
    public function kernel_registers_heartbeat_as_callback_not_shell_command(): void
    {
        $schedule = new Schedule;
        $kernel = $this->app->make(Kernel::class);
        $method = new ReflectionMethod(Kernel::class, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $heartbeat = null;
        foreach ($schedule->events() as $event) {
            if (($event->description ?? '') === 'schedule:heartbeat') {
                $heartbeat = $event;
                break;
            }
        }

        $this->assertNotNull($heartbeat);
        $this->assertInstanceOf(CallbackEvent::class, $heartbeat);
        $this->assertSame('', (string) ($heartbeat->command ?? ''));
    }
}
