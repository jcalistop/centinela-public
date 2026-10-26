<?php

namespace App\Listeners;

use App\Models\PlatformAuthSession;
use App\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

class EndPlatformAuthSession
{
    public function __construct(private readonly Request $request) {}

    public function handle(Logout $event): void
    {
        if (! config('centinela.usage_tracking.enabled', false)) {
            return;
        }

        if (! $event->user instanceof User) {
            return;
        }

        $session = $this->request->hasSession() ? $this->request->session() : null;
        $sessionId = $session?->getId();
        if (! is_string($sessionId) || $sessionId === '') {
            return;
        }

        PlatformAuthSession::query()
            ->where('laravel_session_id', $sessionId)
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->limit(1)
            ->update([
                'ended_at' => now(),
                'end_reason' => 'logout',
            ]);
    }
}
