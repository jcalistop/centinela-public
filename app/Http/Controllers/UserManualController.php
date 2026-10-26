<?php

namespace App\Http\Controllers;

use App\Helpers\ModuleAccess;
use App\Support\UserManual\UserManualRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class UserManualController extends Controller
{
    public function __construct(private readonly UserManualRepository $manuals)
    {
        $this->middleware(function ($request, $next) {
            abort_unless((bool) config('centinela.user_manual.enabled'), 404);
            abort_unless(ModuleAccess::canAccess('manual_usuario'), 403, 'Modulo no disponible para su Servicio de Salud.');

            return $next($request);
        });
    }

    public function context(Request $request)
    {
        $routeName = $request->string('route_name')->toString();
        $path = $request->string('path')->toString();

        if ($routeName === '') {
            $routeName = $this->routeNameFromAdminDashboardPath($path);
        }

        if ($routeName === '') {
            $current = Route::currentRouteName();
            if (is_string($current) && $current !== '' && ! str_starts_with($current, 'user-manual.')) {
                $routeName = $current;
            }
        }

        $id = $this->manuals->resolveIdForContext(
            $routeName !== '' ? $routeName : null,
            $path !== '' ? $path : null,
        );

        $doc = $this->manuals->find($id);

        if (! $doc) {
            return response()->json([
                'id' => $id,
                'title' => 'Ayuda',
                'html' => '<p>No hay manual disponible para esta vista.</p>',
                'meta' => [],
                'url' => null,
            ]);
        }

        return response()->json([
            'id' => $doc->id,
            'title' => $doc->title,
            'html' => $doc->html,
            'meta' => $doc->frontMatter,
            'url' => route('user-manual.show', ['id' => $doc->id]),
        ]);
    }

    public function show(string $id)
    {
        $doc = $this->manuals->find($id);
        abort_if(! $doc, 404);

        return view('centinela.user-manual.show', [
            'doc' => $doc,
        ]);
    }

    public function index()
    {
        return view('centinela.user-manual.index', [
            'pages' => $this->manuals->list(),
        ]);
    }

    /**
     * When Ayuda hace fetch a `user-manual.context`, la ruta actual es la del endpoint, no la de la vista.
     * Si el navegador envía solo `path` y es el inicio del panel (`/admin` o `/admin/`), equivale a `voyager.dashboard`.
     */
    private function routeNameFromAdminDashboardPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $pathOnly = parse_url($path, PHP_URL_PATH);
        if (! is_string($pathOnly) || $pathOnly === '') {
            $pathOnly = $path;
        }

        return preg_match('#^/admin/?$#', $pathOnly) === 1 ? 'voyager.dashboard' : '';
    }
}
