<?php

namespace App\Http\Middleware;

use App\Helpers\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIndicatorTypeModuleEnabled
{
    /**
     * Verifica acceso al modulo cuyo key se lee del route parameter `indicatorType`.
     * Disenado para las rutas con prefijo dinamico `{indicatorType:route}` (metas-18834, metas-19664, etc.).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $indicatorType = $request->route('indicatorType');

        if ($indicatorType !== null && ! ModuleAccess::canAccess($indicatorType->route)) {
            abort(403, 'Modulo no disponible para su Servicio de Salud.');
        }

        return $next($request);
    }
}
