<?php

namespace App\Services\Metas;

use App\Models\MetIndicatorAppeal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MetAppealAttachmentService
{
    private const STORAGE_PREFIX = 'metas/appeals/';

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{path: string, name: string}>
     */
    public function appendUploadedFiles(
        array $files,
        int $yearId,
        int $centerId,
        int $indicatorId,
        array $existing = [],
    ): array {
        $archivos = $existing;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store(
                self::STORAGE_PREFIX.$yearId.'/'.$centerId.'/'.$indicatorId,
                'local',
            );

            $archivos[] = [
                'path' => $path,
                'name' => $file->getClientOriginalName(),
            ];
        }

        return $archivos;
    }

    /**
     * @param  array<int, array{path?: string, name?: string}>  $archivos
     * @return array<int, array{path: string, name: string}>
     */
    public function removeAtIndex(array $archivos, int $index): array
    {
        if (! array_key_exists($index, $archivos)) {
            return array_values($archivos);
        }

        $entry = $archivos[$index];
        if (isset($entry['path']) && is_string($entry['path'])) {
            $this->deleteStoredFile($entry['path']);
        }

        unset($archivos[$index]);

        return array_values($archivos);
    }

    public function deleteStoredFile(string $path): void
    {
        if (! $this->isManagedPath($path)) {
            return;
        }

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function isManagedPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        return Str::startsWith($normalized, self::STORAGE_PREFIX);
    }

    /**
     * @return array{path: string, name: string}
     */
    public function resolveAttachmentForDownload(MetIndicatorAppeal $appeal, int $index): array
    {
        $archivos = $appeal->archivos ?? [];

        if (! array_key_exists($index, $archivos)) {
            abort(404);
        }

        $entry = $archivos[$index];
        $path = $entry['path'] ?? null;
        $name = $entry['name'] ?? 'adjunto';

        if (! is_string($path) || ! $this->isManagedPath($path) || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return [
            'path' => $path,
            'name' => is_string($name) && $name !== '' ? $name : basename($path),
        ];
    }
}
