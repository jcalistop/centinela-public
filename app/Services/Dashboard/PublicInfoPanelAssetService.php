<?php

namespace App\Services\Dashboard;

use App\Models\PublicInfoPanel;
use App\Support\Dashboard\PublicInfoPanelPreview;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve assets de paneles nacionales sin mutar los archivos en disco (SDD-282/283).
 *
 * Guest tipado / relativo: HTML con &lt;base&gt; + CSP para iframe sandboxed + CORS.
 */
final class PublicInfoPanelAssetService
{
    /**
     * Prefijo absoluto para rutas relativas del HTML (termina en /).
     */
    public function assetBaseHref(PublicInfoPanel $panel): string
    {
        if ($panel->publicado) {
            return url('/storage/public-info-panels/'.$panel->slug.'/r').'/';
        }

        return PublicInfoPanelPreview::signedAssetBaseHref($panel->slug);
    }

    public function serveTyped(PublicInfoPanel $panel, string $asset): BinaryFileResponse|StreamedResponse|Response
    {
        $column = match ($asset) {
            'html' => 'html_path',
            'csv' => 'csv_path',
            'preview' => 'preview_png_path',
            default => null,
        };

        if ($column === null) {
            abort(404);
        }

        if ($asset === 'html') {
            return $this->serveHtmlWithBase($panel);
        }

        $absolute = $panel->absoluteAssetPath($column);
        if ($absolute === null) {
            abort(404);
        }

        if ($asset === 'csv') {
            return $this->serveCsvDownloadWithUtf8Bom($absolute);
        }

        return response()->file($absolute, array_merge($this->corsHeaders(), [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }

    public function serveRelative(PublicInfoPanel $panel, string $path): BinaryFileResponse|StreamedResponse|Response
    {
        $path = str_replace('\\', '/', ltrim($path, '/'));
        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $basename = basename($path);
        $column = $this->resolveColumnForRelativePath($panel, $basename, $path);

        if ($column === null) {
            abort(404);
        }

        if ($column === 'html_path') {
            return $this->serveHtmlWithBase($panel);
        }

        $absolute = $panel->absoluteAssetPath($column);
        if ($absolute === null) {
            abort(404);
        }

        $mime = match ($column) {
            'csv_path' => 'text/csv; charset=UTF-8',
            'preview_png_path' => 'image/png',
            default => 'application/octet-stream',
        };

        return response()->file($absolute, array_merge($this->corsHeaders(), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]));
    }

    public function serveHtmlWithBase(PublicInfoPanel $panel): Response
    {
        $absolute = $panel->absoluteAssetPath('html_path');
        if ($absolute === null) {
            abort(404);
        }

        $html = file_get_contents($absolute);
        if ($html === false) {
            abort(404);
        }

        $baseHref = e($this->assetBaseHref($panel));
        $baseTag = '<base href="'.$baseHref.'">';

        if (preg_match('/<base\s/i', $html) === 1) {
            $html = preg_replace('/<base\s[^>]*>/i', $baseTag, $html, 1) ?? $html;
        } elseif (preg_match('/<head([^>]*)>/i', $html) === 1) {
            $html = preg_replace('/<head([^>]*)>/i', '<head$1>'.$baseTag, $html, 1) ?? $html;
        } else {
            $html = $baseTag.$html;
        }

        return response($html, 200, array_merge($this->corsHeaders(), $this->panelHtmlCspHeaders(), [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=60',
        ]));
    }

    /**
     * Descarga tipada: UTF-8 con BOM para que Excel (Windows) muestre acentos bien.
     * No muta el archivo en disco; el fetch relativo del panel sigue sin BOM forzado.
     */
    private function serveCsvDownloadWithUtf8Bom(string $absolute): StreamedResponse
    {
        $contents = file_get_contents($absolute);
        if ($contents === false) {
            abort(404);
        }

        if (! str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = "\xEF\xBB\xBF".$contents;
        }

        return response()->streamDownload(function () use ($contents): void {
            echo $contents;
        }, basename($absolute), array_merge($this->corsHeaders(), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]));
    }

    /**
     * @return array<string, string>
     */
    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        ];
    }

    /**
     * CSP acotada para el documento del iframe (scripts inline permitidos).
     *
     * @return array<string, string>
     */
    private function panelHtmlCspHeaders(): array
    {
        return [
            'Content-Security-Policy' => implode('; ', [
                "default-src 'none'",
                "script-src 'unsafe-inline'",
                "style-src 'unsafe-inline'",
                'img-src data: blob: *',
                'connect-src *',
                'font-src data: *',
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'none'",
                "frame-ancestors 'self'",
            ]),
        ];
    }

    private function resolveColumnForRelativePath(PublicInfoPanel $panel, string $basename, string $path): ?string
    {
        foreach (['html_path', 'csv_path', 'preview_png_path'] as $column) {
            $stored = $panel->{$column} ?? null;
            if (is_string($stored) && $stored !== '' && basename($stored) === $basename) {
                return $column;
            }
        }

        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

        return match ($ext) {
            'csv', 'tsv', 'txt' => 'csv_path',
            'html', 'htm' => 'html_path',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg' => 'preview_png_path',
            default => $this->resolveBySiblingOnDisk($panel, $path),
        };
    }

    private function resolveBySiblingOnDisk(PublicInfoPanel $panel, string $path): ?string
    {
        $htmlRelative = $panel->html_path;
        if (! is_string($htmlRelative) || $htmlRelative === '') {
            return null;
        }

        $dir = str_replace('\\', '/', dirname($htmlRelative));
        if ($dir === '.' || $dir === '') {
            return null;
        }

        $candidate = $dir.'/'.basename($path);
        $disk = Storage::disk(config('voyager.storage.disk', 'private'));
        if (! $disk->exists($candidate)) {
            return null;
        }

        return null;
    }
}
