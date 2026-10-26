<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReadOnlyFilePolicy
{
    /**
     * Capabilities:
     * - settings-images: allow only image files under /storage/settings/*
     * - users-default-avatar: allow only users/default.png under /storage/users/*
     * - deny: deny any access (403) for visualizador
     * - download-document-only: allow /download/* y /view/* solo cuando slug es documentos | documents (BREAD normativa); medios-verificacion queda denegado para visualizador
     * - deny-download: deny download-like endpoints (403) for visualizador
     * - deny-upload: deny upload/import endpoints (403) for visualizador
     * - voyager-file-ops: deny Voyager media manager and upload endpoints (403) for visualizador
     */
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();
        $roleName = $user?->role?->name;

        $isReadOnly = in_array($roleName, ['visualizador'], true);
        if (! $isReadOnly) {
            return $next($request);
        }

        return match ($capability) {
            'settings-images' => $this->handleSettingsImages($request, $next),
            'users-default-avatar' => $this->handleUsersDefaultAvatar($request, $next),
            'download-document-only' => $this->handleDownloadDocumentOnly($request, $next),
            'voyager-file-ops' => $this->handleVoyagerFileOps($request, $next),
            'deny', 'deny-download', 'deny-upload' => abort(403),
            default => abort(500, 'ReadOnlyFilePolicy capability inválida.'),
        };
    }

    private function handleSettingsImages(Request $request, Closure $next): Response
    {
        $filepath = (string) ($request->route('filepath') ?? '');

        // Prevent traversal / weird paths
        if ($filepath === '' || str_contains($filepath, '..') || str_starts_with($filepath, '/')) {
            abort(403);
        }

        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

        if (! in_array($ext, $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }

    private function handleUsersDefaultAvatar(Request $request, Closure $next): Response
    {
        $filepath = (string) ($request->route('filepath') ?? '');

        if ($filepath === '' || str_contains($filepath, '..') || str_starts_with($filepath, '/')) {
            abort(403);
        }

        // Only allow the single default avatar file.
        if ($filepath !== 'default.png') {
            abort(403);
        }

        return $next($request);
    }

    private function handleDownloadDocumentOnly(Request $request, Closure $next): Response
    {
        $slug = (string) ($request->route('slug') ?? '');

        if (! in_array($slug, ['documentos', 'documents'], true)) {
            abort(403);
        }

        return $next($request);
    }

    private function handleVoyagerFileOps(Request $request, Closure $next): Response
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        // Block media manager entirely and generic voyager upload endpoint.
        if (
            str_starts_with($routeName, 'voyager.media.')
            || $routeName === 'voyager.upload'
            || str_ends_with($routeName, '.media.remove')
        ) {
            abort(403);
        }

        return $next($request);
    }
}
