<?php

namespace App\Support\Dashboard;

use App\Models\PublicInfoPanel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Guarda de publicación y de paths de precarga para paneles públicos (SDD-306).
 *
 * Extraída de PublicInfoPanelForm (Filament) para que Voyager y Filament compartan reglas sin acoplarse.
 */
final class PublicInfoPanelPublishGuard
{
    /**
     * Paths written by ZIP preload (or legacy zip-preload/) that Create may submit as strings.
     * Exact basename allowlist + no `..` (Filament warns against bare prefix checks).
     */
    public static function isTrustedPreloadedFilePath(string $file): bool
    {
        $normalized = str_replace('\\', '/', $file);

        if (
            $normalized === ''
            || str_contains($normalized, '..')
            || str_starts_with($normalized, '/')
            || preg_match('#^[A-Za-z]:#', $normalized) === 1
        ) {
            return false;
        }

        $legacyUuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
        $slugOrTmp = 'tmp|[a-z0-9]+(?:-[a-z0-9]+)*';
        $basename = 'panel\.html|datos\.csv|preview\.png';
        $pattern = '#^public-info-panels/(?:zip-preload/'.$legacyUuid.'|'.$slugOrTmp.')/(?:'.$basename.')$#';

        if (preg_match($pattern, $normalized) !== 1) {
            return false;
        }

        return Storage::disk(config('voyager.storage.disk', 'private'))->exists($normalized);
    }

    /**
     * Guarda de publicación para superficies sin formulario (toggle del listado, SDD-303).
     *
     * Reutiliza las mismas reglas que el formulario sobre los atributos persistidos.
     */
    public static function assertPublishableRecord(PublicInfoPanel $record): void
    {
        self::assertPublishable([
            'tipo' => $record->tipo,
            'descripcion' => $record->descripcion,
            'autor_email' => $record->autor_email,
            'publicado' => true,
            'preview_png_path' => $record->preview_png_path,
            'embed_url' => $record->embed_url,
            'html_path' => $record->html_path,
            'csv_path' => $record->csv_path,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function assertPublishable(array $data): array
    {
        $tipo = $data['tipo'] ?? PublicInfoPanel::TIPO_HTML_BUNDLE;
        if ($tipo === PublicInfoPanel::TIPO_HTML_BUNDLE) {
            self::assertHtmlClean($data);
        }

        if (! filled($data['descripcion'] ?? null)) {
            throw ValidationException::withMessages([
                'descripcion' => 'La descripción es obligatoria.',
            ]);
        }

        $autorEmail = $data['autor_email'] ?? null;
        if (filled($autorEmail) && filter_var((string) $autorEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw ValidationException::withMessages([
                'autor_email' => 'El correo del autor no es válido.',
            ]);
        }

        if (! ($data['publicado'] ?? false)) {
            return $data;
        }

        if (! filled($data['preview_png_path'] ?? null)) {
            throw ValidationException::withMessages([
                'preview_png_path' => 'Para publicar el panel se requiere PNG de preview.',
            ]);
        }

        if ($tipo === PublicInfoPanel::TIPO_EMBED_EXTERNO) {
            $url = $data['embed_url'] ?? null;
            if (! filled($url) || ! PublicInfoPanelEmbedUrl::isAllowed(is_string($url) ? $url : null)) {
                throw ValidationException::withMessages([
                    'embed_url' => 'Para publicar un embed se requiere una URL https en un host allowlist.',
                ]);
            }

            return $data;
        }

        foreach (['html_path', 'csv_path'] as $field) {
            if (! filled($data[$field] ?? null)) {
                throw ValidationException::withMessages([
                    $field => 'Para publicar el panel se requieren HTML, CSV y PNG de preview.',
                ]);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function assertHtmlClean(array $data): void
    {
        $path = $data['html_path'] ?? null;
        if (is_array($path)) {
            $path = $path[0] ?? null;
        }

        if (! is_string($path) || $path === '') {
            return;
        }

        $disk = Storage::disk(config('voyager.storage.disk', 'private'));
        if (! $disk->exists($path)) {
            return;
        }

        $html = $disk->get($path);
        if (! is_string($html)) {
            return;
        }

        app(PublicInfoPanelHtmlScanner::class)->assertClean($html);
    }
}
