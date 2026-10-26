<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\PlatformAreaUsageSample;
use App\Models\User;
use App\Support\PlatformUsage\AreaClassifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RecordPlatformUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = strtolower(trim((string) $request->path(), '/'));
        if (! str_starts_with($path, 'admin')) {
            return $next($request);
        }

        $response = $next($request);

        $enabled = (bool) config('centinela.usage_tracking.enabled', false);

        if (! $enabled) {
            return $response;
        }

        $user = Auth::user() ?? $request->user();

        if (! $user instanceof User) {
            return $response;
        }

        $session = $request->hasSession() ? $request->session() : null;
        $sessionId = $session?->getId();

        if (! is_string($sessionId) || $sessionId === '') {
            return $response;
        }

        $area = AreaClassifier::classify($request);

        $ttl = (int) config('centinela.usage_tracking.throttle_seconds', 300);
        if ($ttl <= 0) {
            $ttl = 300;
        }

        $sessionThrottleKey = "platform_usage_throttle.{$area}";
        $last = (int) ($session?->get($sessionThrottleKey) ?? 0);
        if ($last > 0 && (time() - $last) < $ttl) {
            return $response;
        }

        PlatformAreaUsageSample::query()->create([
            'user_id' => $user->getKey(),
            'cen_hie_dependency_id' => $user->cen_hie_dependency_id,
            'area' => $area,
            'laravel_session_id' => $sessionId,
            'created_at' => now(),
        ]);

        $session?->put($sessionThrottleKey, time());

        return $response;
    }
}
