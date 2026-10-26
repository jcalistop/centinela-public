<?php

namespace App\Support\Schedule;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Stringable;

/**
 * Adjunta logging enriquecido a eventos del scheduler (SDD-223 DR-1).
 */
final class ScheduleEventLogging
{
    public static function onFailure(Event $event, string $commandLabel, string $errorMessage): Event
    {
        return $event->onFailureWithOutput(function (Stringable $output) use ($event, $commandLabel, $errorMessage) {
            Log::error($errorMessage, self::context($commandLabel, $event->exitCode, (string) $output));
        });
    }

    public static function onSuccess(Event $event, string $commandLabel, string $successMessage): Event
    {
        return $event->onSuccessWithOutput(function (Stringable $output) use ($event, $commandLabel, $successMessage) {
            Log::info($successMessage, self::context($commandLabel, $event->exitCode, (string) $output));
        });
    }

    /**
     * @return array{command: string, exit_code: int|null, hostname: string|false, timezone: mixed, triggered_via_http: bool, output: string}
     */
    public static function context(string $commandLabel, ?int $exitCode, string $output): array
    {
        return [
            'command' => $commandLabel,
            'exit_code' => $exitCode,
            'hostname' => gethostname(),
            'timezone' => config('app.timezone'),
            'triggered_via_http' => (bool) config('centinela.schedule.triggered_via_http', false),
            'output' => ScheduleOutputRedactor::redact($output),
        ];
    }
}
