<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventConcurrentRequests
{
    /**
     * Evita que el mismo endpoint sea ejecutado concurrentemente usando un lock de archivo.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $lockKey = str_replace(['/', '\\', ' '], '_', ltrim($request->getPathInfo(), '/'));
        $lockFile = storage_path("temp/lock_{$lockKey}.lock");

        $dir = dirname($lockFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($lockFile, 'c');

        if (! $fp || ! flock($fp, LOCK_EX | LOCK_NB)) {
            if ($fp) {
                fclose($fp);
            }

            return response()->json([
                'success' => false,
                'message' => 'Otra instancia de esta operación ya está en ejecución. Intente nuevamente en unos momentos.',
            ], 429);
        }

        try {
            $response = $next($request);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return $response;
    }
}
