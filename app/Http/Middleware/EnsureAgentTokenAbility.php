<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Agent\AgentErrorEnvelope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgentTokenAbility
{
    public function handle(Request $request, Closure $next, string $ability = 'read'): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->tokenCan($ability)) {
            return AgentErrorEnvelope::json(
                'FORBIDDEN',
                'El token no tiene la ability requerida.',
                403,
                ['ability' => $ability]
            );
        }

        return $next($request);
    }
}
