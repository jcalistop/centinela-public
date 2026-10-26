<?php

namespace Tests\Unit\Console;

use App\Console\Kernel;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use ReflectionMethod;
use Tests\TestCase;

class KernelScheduleHoursTest extends TestCase
{
    /**
     * @return list<array{label: string, expression: string, command: string}>
     */
    private function scheduledEvents(): array
    {
        $schedule = new Schedule;
        $kernel = $this->app->make(Kernel::class);
        $method = new ReflectionMethod(Kernel::class, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $found = [];
        foreach ($schedule->events() as $event) {
            /** @var Event $event */
            $command = (string) ($event->command ?? '');
            $description = (string) ($event->description ?? '');
            $label = $command !== '' ? $command : $description;
            $found[] = [
                'label' => $label,
                'expression' => $event->expression,
                'command' => $command,
            ];
        }

        return $found;
    }

    private function expressionFor(string $needle): ?string
    {
        foreach ($this->scheduledEvents() as $event) {
            if (str_contains($event['label'], $needle) || str_contains($event['command'], $needle)) {
                return $event['expression'];
            }
        }

        return null;
    }

    /** @test */
    public function nightly_batch_runs_at_midnight_not_noon(): void
    {
        $this->assertNull($this->expressionFor('schedule:daily-report'));
        $this->assertSame('0 0 * * *', $this->expressionFor('db:backup'));
        $this->assertSame('0 0 * * *', $this->expressionFor('audit:cleanup'));
        $this->assertSame('0 0 * * *', $this->expressionFor('security:audit-check'));
        $this->assertSame('0 0 * * *', $this->expressionFor('centinela:support-close-resolved'));
        $this->assertSame('5 0 * * *', $this->expressionFor('centinela:clean-storage-temp'));
        $this->assertSame('10 0 * * *', $this->expressionFor('cen:sync-runs:prune'));
        $this->assertSame('15 0 * * *', $this->expressionFor('centinela:prune-schedule-logs'));
    }

    /** @test */
    public function heartbeat_and_close_sessions_are_hourly_in_process(): void
    {
        $this->assertSame('0 * * * *', $this->expressionFor('schedule:heartbeat'));
        $this->assertSame('0 * * * *', $this->expressionFor('platform:close-expired-auth-sessions'));
    }

    /** @test */
    public function db_backup_skip_if_recent_is_a_valueless_flag(): void
    {
        $command = null;
        foreach ($this->scheduledEvents() as $event) {
            if (str_contains($event['command'], 'db:backup') || str_contains($event['label'], 'db:backup')) {
                $command = $event['command'] !== '' ? $event['command'] : $event['label'];
                break;
            }
        }

        $this->assertNotNull($command);
        $this->assertStringContainsString('--skip-if-recent', $command);
        $this->assertDoesNotMatchRegularExpression('/--skip-if-recent=/', $command);
    }
}
