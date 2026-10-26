<?php

namespace App\Http\Middleware;

use App\Support\Configuration\ConfigurationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated Usar solo `module.access:soporte` en rutas. Se conserva por compatibilidad en Kernel.
 */
class EnsureCentinelaSupportEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(ConfigurationService::class)->isRadioOn('module.soporte_enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
