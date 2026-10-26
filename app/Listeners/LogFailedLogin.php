<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;

class LogFailedLogin
{
    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        try {
            $request = request();
            $email = $event->credentials['email'] ?? $event->credentials['username'] ?? null;

            $entry = json_encode([
                'timestamp' => now()->toIso8601String(),
                'email' => is_string($email) ? $email : null,
                'user_id' => $event->user?->id,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'guard' => $event->guard,
            ]);

            file_put_contents(
                storage_path('logs/failed-logins.log'),
                $entry."\n",
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar intento de login fallido', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
