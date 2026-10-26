<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateCronToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Cron-Token');
        $expectedToken = config('app.cron_secret_token');

        if (! $expectedToken) {
            return response()->json([
                'error' => 'Cron token no configurado en el servidor',
            ], 500);
        }

        if (! $token || $token !== $expectedToken) {
            return response()->json([
                'error' => 'Token de autenticación inválido',
            ], 401);
        }

        return $next($request);
    }
}
