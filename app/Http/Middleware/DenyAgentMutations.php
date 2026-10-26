<?php

namespace App\Http\Middleware;

use App\Support\Agent\AgentErrorEnvelope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AG-27: superficie agentes solo lectura (GET/HEAD/OPTIONS).
 */
class DenyAgentMutations
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return AgentErrorEnvelope::json(
                'METHOD_NOT_ALLOWED',
                'La superficie de agentes solo admite lectura (GET).',
                405
            );
        }

        return $next($request);
    }
}
