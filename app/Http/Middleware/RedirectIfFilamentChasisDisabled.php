<?php

namespace App\Http\Middleware;

use App\Support\Configuration\FilamentChasisGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kill-switch chasis Filament (SDD-308): maestro off → 404.
 */
final class RedirectIfFilamentChasisDisabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! FilamentChasisGate::enabled()) {
            abort(404);
        }

        return $next($request);
    }
}
