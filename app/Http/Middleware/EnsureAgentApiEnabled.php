<?php

namespace App\Http\Middleware;

use App\Support\Agent\AgentErrorEnvelope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgentApiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('centinela.agent_api.enabled', false)) {
            return AgentErrorEnvelope::json(
                'AGENT_API_DISABLED',
                'La API de agentes está deshabilitada.',
                403
            );
        }

        return $next($request);
    }
}
