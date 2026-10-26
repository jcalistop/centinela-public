<?php

namespace App\Http\Middleware;

use App\Helpers\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureComgesBreadModuleEnabled
{
    /** @var list<string> */
    private const COMGES_SLUGS = [
        'compromisos',
        'indicadores-comges',
        'acciones',
        'evaluaciones-comges',
        'cortes-comges',
        'medios-verificacion',
        'configurar-indicador-comges',
        'tablas-sensibilidad-comges',
        'visualizaciones',
        'com-sensitivity-dependencies',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if ($routeName !== null) {
            foreach (self::COMGES_SLUGS as $slug) {
                if (str_starts_with($routeName, 'voyager.'.$slug)) {
                    if (! ModuleAccess::canAccess('comges')) {
                        abort(403, 'Modulo no disponible para su Servicio de Salud.');
                    }

                    break;
                }
            }
        }

        return $next($request);
    }
}
