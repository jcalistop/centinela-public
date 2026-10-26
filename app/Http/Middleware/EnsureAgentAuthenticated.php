<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Agent\AgentErrorEnvelope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * S-05 / AG-03: autenticar vía Sanctum; sin usuario → denegar con envelope (nunca datos).
 */
class EnsureAgentAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user instanceof User) {
            return AgentErrorEnvelope::json(
                'UNAUTHENTICATED',
                'Se requiere autenticación Sanctum para la superficie de agentes.',
                401
            );
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
