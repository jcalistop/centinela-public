<?php

namespace App\Listeners;

use App\Models\PlatformAuthSession;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class StartPlatformAuthSession
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
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

        // Una sola sesión abierta por usuario: cierra las previas al nuevo login (SDD-087).
        PlatformAuthSession::query()
            ->where('user_id', $event->user->getKey())
            ->whereNull('ended_at')
            ->update([
                'ended_at' => now(),
                'end_reason' => 'superseded',
            ]);

        PlatformAuthSession::query()->create([
            'user_id' => $event->user->getKey(),
            'cen_hie_dependency_id' => $event->user->cen_hie_dependency_id,
            'laravel_session_id' => $sessionId,
            'started_at' => now(),
        ]);
    }
}
