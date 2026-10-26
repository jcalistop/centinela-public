<?php

namespace App\Support\ChatIa;

use App\Models\IndicatorType;
use App\Models\User;
use App\Models\Year;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

final class PageContextResolver
{
    /**
     * @param  string|null  $browserPath  Path HTTP de la página (p. ej. "/admin/compromisos"). En Livewire suele
     *                                    pasarse el valor capturado en el mount para no usar "/livewire/update".
     * @param  string|null  $browserRouteName  Nombre de ruta de la página (p. ej. "voyager.compromisos.index"), no el de livewire.update.
     */
    public static function fromRequest(Request $request, ?string $browserPath = null, ?string $browserRouteName = null): PageContext
    {
        $path = self::normalizeBrowserPath($browserPath, $request);
        $routeName = self::normalizeBrowserRouteName($browserRouteName, $request);

        $useRouteBinding = $request->route()?->getName() !== 'livewire.update';

        $indicatorTypeId = self::resolveIndicatorTypeId($request, $path, $routeName, $useRouteBinding);
        $yearId = self::resolveYearId();
        $cenHieDependencyId = self::resolveCenHieDependencyId();

        $extra = [
            'method' => $request->method(),
            'year_source' => 'session:indicator_year',
            'cen_hie_source' => 'User::cen_hie_dependency_id',
        ];

        if ($request->route()?->getName() === 'livewire.update') {
            $extra['http_route_name'] = 'livewire.update';
        }

        return new PageContext(
            routeName: $routeName,
            path: $path,
            indicatorTypeId: $indicatorTypeId,
            yearId: $yearId,
            cenHieDependencyId: $cenHieDependencyId,
            extra: $extra,
        );
    }

    private static function normalizeBrowserPath(?string $browserPath, Request $request): string
    {
        if (is_string($browserPath) && $browserPath !== '') {
            return str_starts_with($browserPath, '/') ? $browserPath : '/'.$browserPath;
        }

        return '/'.ltrim($request->path(), '/');
    }

    private static function normalizeBrowserRouteName(?string $browserRouteName, Request $request): string
    {
        if (is_string($browserRouteName) && $browserRouteName !== '') {
            return $browserRouteName;
        }

        return (string) ($request->route()?->getName() ?? 'unknown');
    }

    /**
     * @param  bool  $useRouteBinding  false en peticiones Livewire: no hay `{indicatorType}` en la ruta interna.
     */
    private static function resolveIndicatorTypeId(Request $request, string $path, string $routeName, bool $useRouteBinding): ?int
    {
        if ($useRouteBinding) {
            $route = $request->route();
            $param = $route?->parameter('indicatorType');

            if ($param instanceof IndicatorType) {
                return (int) $param->getKey();
            }

            if (is_string($param) && $param !== '') {
                $row = IndicatorType::query()->where('route', $param)->where('estado', true)->first();
                if ($row instanceof IndicatorType) {
                    return (int) $row->getKey();
                }
            }
        }

        if ($routeName !== '') {
            $row = IndicatorType::query()->where('route', $routeName)->where('estado', true)->first();
            if ($row instanceof IndicatorType) {
                return (int) $row->getKey();
            }
        }

        $pathTrim = trim($path, '/');
        $firstSegment = $pathTrim !== '' ? explode('/', $pathTrim)[0] : '';
        if ($firstSegment !== '') {
            $row = IndicatorType::query()->where('route', $firstSegment)->where('estado', true)->first();
            if ($row instanceof IndicatorType) {
                return (int) $row->getKey();
            }
        }

        $fromAdmin = self::resolveIndicatorTypeIdFromAdminVoyagerPath($pathTrim);
        if ($fromAdmin !== null) {
            return $fromAdmin;
        }

        $comgesId = self::resolveComgesIndicatorTypeId($pathTrim, $routeName);

        return $comgesId;
    }

    /**
     * BREAD Voyager: /admin/{slug} donde {slug} coincide con {@see IndicatorType::$route} (p. ej. compromisos).
     */
    private static function resolveIndicatorTypeIdFromAdminVoyagerPath(string $pathTrim): ?int
    {
        if ($pathTrim === '') {
            return null;
        }

        $parts = explode('/', $pathTrim);
        if (($parts[0] ?? '') !== 'admin') {
            return null;
        }

        $slug = $parts[1] ?? '';
        if ($slug === '') {
            return null;
        }

        $row = IndicatorType::query()->where('route', $slug)->where('estado', true)->first();

        return $row instanceof IndicatorType ? (int) $row->getKey() : null;
    }

    /**
     * Rutas Voyager de COMGES no llevan binding `indicatorType`; se ancla al tipo configurado si no hubo match por slug.
     */
    private static function resolveComgesIndicatorTypeId(string $pathTrim, string $routeName): ?int
    {
        $pathLower = strtolower($pathTrim);

        $matchesRoute = $routeName !== '' && str_starts_with($routeName, 'voyager.')
            && (
                str_contains($routeName, 'compromisos')
                || str_contains($routeName, 'indicadores-comges')
                || str_contains($routeName, 'cortes-comges')
                || str_contains($routeName, 'sensibilidad-comges')
                || str_contains($routeName, 'visualizacion-comges')
                || str_contains($routeName, 'tablas-sensibilidad-comges')
                || str_contains($routeName, 'configurar-indicador-comges')
                || str_contains($routeName, 'acciones-comges')
                || str_contains($routeName, 'evaluaciones-comges')
            );

        $matchesPath = str_contains($pathLower, 'admin/compromisos')
            || str_contains($pathLower, 'admin/indicadores-comges')
            || str_contains($pathLower, 'admin/cortes-comges');

        if (! $matchesRoute && ! $matchesPath) {
            return null;
        }

        $id = (int) config('centinela.chat_ia.comges_indicator_type_id', 1);
        if ($id <= 0) {
            return null;
        }

        $exists = IndicatorType::query()->whereKey($id)->where('estado', true)->exists();

        return $exists ? $id : null;
    }

    private static function resolveYearId(): ?int
    {
        $year = Session::get('indicator_year');

        if ($year instanceof Year) {
            return (int) $year->getKey();
        }

        if (is_array($year) && isset($year['id']) && is_numeric($year['id'])) {
            return (int) $year['id'];
        }

        return null;
    }

    private static function resolveCenHieDependencyId(): ?int
    {
        $user = Auth::user();

        if ($user instanceof User && $user->cen_hie_dependency_id !== null) {
            return (int) $user->cen_hie_dependency_id;
        }

        return null;
    }
}
