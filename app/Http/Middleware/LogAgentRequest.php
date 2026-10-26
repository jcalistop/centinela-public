<?php

namespace App\Http\Middleware;

use App\Models\AgentRequestLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogAgentRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $requestId = (string) Str::uuid();
        $request->attributes->set('agent_request_id', $requestId);

        $response = $next($request);

        try {
            $user = $request->user();
            AgentRequestLog::query()->create([
                'request_id' => $requestId,
                'user_id' => $user instanceof User ? $user->id : null,
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'query_hash' => hash('sha256', (string) $request->getQueryString()),
                'status_code' => $response->getStatusCode(),
                'ip' => $request->ip(),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        } catch (Throwable) {
            // No fallar la respuesta por auditoría.
        }

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
