<?php

namespace App\Support\Schedule;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Registra comandos del schedule que se ejecutan in-process (Artisan::call).
 *
 * Evita subprocess/shell (`proc_open`) que en IIS vía POST /api/schedule/run
 * fallan con exit_code=1 aunque el mismo comando funcione en CLI (cron_mail.bat).
 * SDD-243.
 */
final class InProcessArtisanSchedule
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function command(Schedule $schedule, string $command, array $parameters = []): CallbackEvent
    {
        $event = $schedule->call(function () use ($command, $parameters): void {
            $parameters = array_merge(['--no-interaction' => true], $parameters);
            $exitCode = Artisan::call($command, $parameters);
            if ($exitCode !== 0) {
                $output = trim(Artisan::output());
                throw new RuntimeException(
                    sprintf('%s exited with code %d%s', $command, $exitCode, $output !== '' ? ': '.$output : '')
                );
            }
        })->name($command);

        return $event;
    }
}
