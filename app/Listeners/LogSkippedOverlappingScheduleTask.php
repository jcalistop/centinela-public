<?php

namespace App\Listeners;

use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Support\Facades\Log;

/**
 * Detecta omitidos por withoutOverlapping (mutex activo): exitCode queda null
 * porque Event::run retorna antes de finish() (SDD-223 error path).
 */
class LogSkippedOverlappingScheduleTask
{
    public function handle(ScheduledTaskFinished $event): void
    {
        $task = $event->task;

        if (! $task->withoutOverlapping) {
            return;
        }

        if ($task->exitCode !== null) {
            return;
        }

        Log::info('Scheduler: tarea omitida por withoutOverlapping (mutex activo)', [
            'command' => $task->command,
            'hostname' => gethostname(),
            'timezone' => config('app.timezone'),
            'triggered_via_http' => (bool) config('centinela.schedule.triggered_via_http', false),
        ]);
    }
}
