<?php

namespace App\Support\Dashboard;

use App\Models\PublicInfoPanel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Precarga segura de paneles públicos desde ZIP (SDD-291). No persiste ni publica.
 */
final class PublicInfoPanelZipPreloader
{
    private const ALLOWED_BASENAMES = [
        'manifest.json',
        'panel.json',
        'panel.html',
        'datos.csv',
        'preview.png',
    ];

    /**
     * @return array<string, mixed> Estado de formulario a aplicar con $set / fill
     */
    public function preloadFromPath(string $zipAbsolutePath): array
    {
        $maxBytes = (int) config('centinela.public_panel_zip.max_bytes', 30 * 1024 * 1024);
        $maxEntries = (int) config('centinela.public_panel_zip.max_entries', 12);
        $maxUncompressed = (int) config('centinela.public_panel_zip.max_uncompressed_bytes', 80 * 1024 * 1024);

        if (! is_file($zipAbsolutePath)) {
            throw ValidationException::withMessages([
                'zip_precarga' => 'No se pudo leer el archivo ZIP.',
            ]);
        }

        $size = filesize($zipAbsolutePath);
        if ($size === false || $size > $maxBytes) {
            throw ValidationException::withMessages([
                'zip_precarga' => 'El ZIP supera el tamaño máximo permitido ('.round($maxBytes / 1048576, 1).' MB).',
            ]);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipAbsolutePath) !== true) {
            throw ValidationException::withMessages([
                'zip_precarga' => 'El archivo no es un ZIP válido.',
            ]);
        }

        try {
            if ($zip->numFiles > $maxEntries) {
                throw ValidationException::withMessages([
                    'zip_precarga' => 'El ZIP tiene demasiadas entradas.',
                ]);
            }

            $extracted = [];
            $uncompressedTotal = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    continue;
                }

                $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }

                if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name) === 1) {
                    throw ValidationException::withMessages([
                        'zip_precarga' => 'El ZIP contiene rutas no permitidas (path traversal).',
                    ]);
                }

                $basename = strtolower(basename($name));
                if (! in_array($basename, self::ALLOWED_BASENAMES, true)) {
                    throw ValidationException::withMessages([
                        'zip_precarga' => "Entrada no permitida en el contrato: {$basename}",
                    ]);
                }

                $uncompressedTotal += (int) ($stat['size'] ?? 0);
                if ($uncompressedTotal > $maxUncompressed) {
                    throw ValidationException::withMessages([
                        'zip_precarga' => 'El contenido descomprimido del ZIP supera el límite permitido.',
                    ]);
                }

                $contents = $zip->getFromIndex($i);
                if (! is_string($contents)) {
                    throw ValidationException::withMessages([
                        'zip_precarga' => "No se pudo leer la entrada: {$basename}",
                    ]);
                }

                $extracted[$basename] = $contents;
            }
        } finally {
            $zip->close();
        }

        return $this->buildFormState($extracted);
    }

    /**
     * @param  array<string, string>  $extracted
     * @return array<string, mixed>
     */
    private function buildFormState(array $extracted): array
    {
        $manifestRaw = $extracted['manifest.json'] ?? $extracted['panel.json'] ?? null;
        if ($manifestRaw === null) {
            throw ValidationException::withMessages([
                'zip_precarga' => 'Falta manifest.json (o panel.json) en el ZIP.',
            ]);
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($manifestRaw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                'zip_precarga' => 'El manifest JSON no es válido.',
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'zip_precarga' => 'El manifest debe ser un objeto JSON.',
            ]);
        }

        $titulo = trim((string) ($decoded['titulo'] ?? ''));
        $descripcion = trim((string) ($decoded['descripcion'] ?? ''));
        $tipo = (string) ($decoded['tipo'] ?? PublicInfoPanel::TIPO_HTML_BUNDLE);

        if ($titulo === '' || $descripcion === '') {
            throw ValidationException::withMessages([
                'zip_precarga' => 'El manifest requiere titulo y descripcion.',
            ]);
        }

        if (! in_array($tipo, [PublicInfoPanel::TIPO_HTML_BUNDLE, PublicInfoPanel::TIPO_EMBED_EXTERNO], true)) {
            throw ValidationException::withMessages([
                'zip_precarga' => 'tipo debe ser html_bundle o embed_externo.',
            ]);
        }

        if (! isset($extracted['preview.png'])) {
            throw ValidationException::withMessages([
                'zip_precarga' => 'Falta preview.png en el ZIP.',
            ]);
        }

        $disk = Storage::disk(config('voyager.storage.disk', 'private'));

        $state = [
            'titulo' => Str::limit($titulo, 100, ''),
            'descripcion' => Str::limit($descripcion, 400, ''),
            'tipo' => $tipo,
            'publicado' => false,
        ];

        $slug = trim((string) ($decoded['slug'] ?? ''));
        if ($slug !== '') {
            $state['slug'] = Str::slug($slug);
        } else {
            $state['slug'] = Str::slug($titulo);
        }

        $slugSegment = filled($state['slug'] ?? null) ? (string) $state['slug'] : 'tmp';
        // Same prefix as FileUpload::directory() so Create/Edit paths stay coherent (SDD-292).
        $dir = 'public-info-panels/'.$slugSegment;

        foreach (['autor', 'autor_email', 'institucion', 'version_panel'] as $meta) {
            if (array_key_exists($meta, $decoded) && filled($decoded[$meta])) {
                $state[$meta] = is_scalar($decoded[$meta]) ? (string) $decoded[$meta] : null;
            }
        }

        if ($tipo === PublicInfoPanel::TIPO_EMBED_EXTERNO) {
            $embedUrl = trim((string) ($decoded['embed_url'] ?? ''));
            if ($embedUrl === '') {
                throw ValidationException::withMessages([
                    'zip_precarga' => 'embed_externo requiere embed_url en el manifest.',
                ]);
            }
            if (! PublicInfoPanelEmbedUrl::isAllowed($embedUrl)) {
                throw ValidationException::withMessages([
                    'zip_precarga' => 'embed_url no está en la allowlist https de hosts permitidos.',
                ]);
            }
            $state['embed_url'] = $embedUrl;
            $state['html_path'] = null;
            $state['csv_path'] = null;
        } else {
            foreach (['panel.html' => 'html_path', 'datos.csv' => 'csv_path'] as $file => $field) {
                if (! isset($extracted[$file])) {
                    throw ValidationException::withMessages([
                        'zip_precarga' => "Falta {$file} para html_bundle.",
                    ]);
                }
            }
            $state['embed_url'] = null;
            $disk->put($dir.'/panel.html', $extracted['panel.html']);
            $disk->put($dir.'/datos.csv', $extracted['datos.csv']);
            $state['html_path'] = $dir.'/panel.html';
            $state['csv_path'] = $dir.'/datos.csv';
        }

        $disk->put($dir.'/preview.png', $extracted['preview.png']);
        $state['preview_png_path'] = $dir.'/preview.png';

        return $state;
    }
}
