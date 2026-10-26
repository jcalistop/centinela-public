<?php

namespace App\Http\Middleware;

use App\Helpers\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    /**
     * Verifica que el modulo especificado este accesible para el SS del usuario.
     * Admin siempre pasa.
     *
     * Uso en rutas: ->middleware('module.access:adp')
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        if (! ModuleAccess::canAccess($moduleKey)) {
            abort(403, 'Modulo no disponible para su Servicio de Salud.');
        }

        return $next($request);
    }
}
