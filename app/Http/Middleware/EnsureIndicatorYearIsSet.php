<?php

namespace App\Http\Middleware;

use App\Support\YearContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class EnsureIndicatorYearIsSet
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = strtolower(trim((string) $request->path(), '/'));
        if (! str_starts_with($path, 'admin')) {
            return $next($request);
        }

        // Evitar loop de redirección: el login de Voyager vive bajo /admin/login
        // y este middleware se ejecuta para todo admin/*.
        $routeName = $request->route()?->getName();
        if ($path === 'admin/login' || $routeName === 'voyager.login' || $routeName === 'voyager.postlogin' || $routeName === 'voyager.voyager_assets') {
            return $next($request);
        }

        if (! Session::has(YearContext::SESSION_KEY) || Session::get(YearContext::SESSION_KEY) === null) {
            if (! Auth::check()) {
                return redirect()->guest(route('login'));
            }

            app(YearContext::class)->ensureDefault();
        }

        return $next($request);
    }
}
