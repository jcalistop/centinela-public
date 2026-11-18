<?php

namespace App\Services\Agent;

use App\Models\Document;
use App\Models\IndicatorType;
use App\Models\Year;
use App\Support\Storage\RelativePathGuard;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentsReadAdapter
{
    /** @var list<string> */
    private const INSTRUMENTOS = ['comges', 'metas', 'adp'];

    /** @var array<string, string> */
    private const LEY_ROUTES = [
        '18834' => 'metas-18834',
        '19664' => 'metas-19664',
        '20707' => 'metas-20707',
    ];

    /**
     * @return array{ok: true, type_ids: list<int>|null}|array{ok: false, reason: 'ausente'}
     */
    public function resolveTypeFilter(?string $ley, ?string $instrumento): array
    {
        $leyNorm = $this->normalizedLey($ley);
        $instNorm = $this->normalizedInstrumento($instrumento);

        if ($ley !== null && $ley !== '' && $leyNorm === null) {
            return ['ok' => false, 'reason' => 'ausente'];
        }

        if ($instrumento !== null && $instrumento !== '' && $instNorm === null) {
            return ['ok' => false, 'reason' => 'ausente'];
        }

        if ($leyNorm !== null && $instNorm !== null && $instNorm !== 'metas') {
            return ['ok' => false, 'reason' => 'ausente'];
        }

        if ($leyNorm !== null) {
            $ids = $this->typeIdsForRoutes([self::LEY_ROUTES[$leyNorm]]);

            return ['ok' => true, 'type_ids' => $ids];
        }

        if ($instNorm === null) {
            return ['ok' => true, 'type_ids' => null];
        }

        $routes = match ($instNorm) {
            'comges' => ['comges'],
            'adp' => ['adp'],
            'metas' => array_values(self::LEY_ROUTES),
            default => [],
        };

        return ['ok' => true, 'type_ids' => $this->typeIdsForRoutes($routes)];
    }

    /**
     * @param  list<int>|null  $typeIds  null = sin filtro por tipo
     * @return LengthAwarePaginator<int, Document>
     */
    public function paginate(Year $year, int $ssId, ?array $typeIds, int $perPage, int $page): LengthAwarePaginator
    {
        if (is_array($typeIds) && $typeIds === []) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $query = Document::query()
            ->where('year_id', $year->id)
            ->where('cen_hie_dependency_id', $ssId)
            ->with([
                'indicatorType:id,clave_indicador,nombre_indicador,route',
                'user:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (is_array($typeIds)) {
            $query->whereIn('indicator_type_id', $typeIds);
        }

        /** @var LengthAwarePaginator<int, Document> $pageResult */
        $pageResult = $query->paginate($perPage, ['*'], 'page', $page);

        return $pageResult;
    }

    public function findInSs(int $id, int $ssId): ?Document
    {
        if ($id < 1) {
            return null;
        }

        $document = Document::query()
            ->with([
                'indicatorType:id,clave_indicador,nombre_indicador,route',
                'user:id,name',
            ])
            ->find($id);

        if (! $document instanceof Document) {
            return null;
        }

        if ((int) $document->cen_hie_dependency_id !== $ssId) {
            return null;
        }

        return $document;
    }

    /**
     * @return array<string, mixed>
     */
    public function mapDocument(Document $document): array
    {
        $type = $document->relationLoaded('indicatorType')
            ? $document->getRelation('indicatorType')
            : null;
        if (! $type instanceof IndicatorType && $document->indicator_type_id) {
            $type = IndicatorType::query()
                ->select(['id', 'clave_indicador', 'nombre_indicador', 'route'])
                ->find($document->indicator_type_id);
        }

        $route = $type instanceof IndicatorType ? (is_string($type->route) ? $type->route : null) : null;
        $fileMeta = $this->resolvePrimaryFileMeta($document);

        $usuario = null;
        $user = $document->relationLoaded('user') ? $document->getRelation('user') : null;
        if ($user !== null && isset($user->id)) {
            $usuario = [
                'id' => (int) $user->id,
                'nombre' => is_string($user->name ?? null) ? $user->name : '',
            ];
        }

        return [
            'id' => (int) $document->id,
            'nombre' => (string) $document->nombre,
            'descripcion' => (string) $document->descripcion,
            'autor' => (string) $document->autor,
            'indicator_type_id' => (int) $document->indicator_type_id,
            'tipo' => $type instanceof IndicatorType ? [
                'clave' => (string) $type->clave_indicador,
                'nombre' => (string) $type->nombre_indicador,
                'route' => $route,
            ] : null,
            'ley' => $this->leyFromRoute($route),
            'instrumento' => $this->instrumentoFromRoute($route),
            'fecha_carga' => $document->created_at?->toIso8601String(),
            'usuario' => $usuario,
            'tamaño_bytes' => $fileMeta['tamaño_bytes'],
            'hash' => null,
            'archivo' => $fileMeta['archivo'],
        ];
    }

    /**
     * Primera entrada válida de `file` (índice + metadatos sin `download_link`).
     *
     * @return array{file_index: int, download_link: string, original_name: string}|null
     */
    public function primaryFileEntry(Document $document): ?array
    {
        $entries = $this->decodeFileEntries($document);
        foreach ($entries as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $link = $entry['download_link'] ?? null;
            if (! is_string($link) || $link === '' || ! RelativePathGuard::isSafe($link)) {
                continue;
            }

            $original = is_string($entry['original_name'] ?? null) ? $entry['original_name'] : 'archivo';

            return [
                'file_index' => (int) $index,
                'download_link' => $link,
                'original_name' => $original,
            ];
        }

        return null;
    }

    public function ttlMinutes(): int
    {
        $ttl = (int) config('centinela.agent_document_url_ttl_minutes', 10);

        return $ttl > 0 ? $ttl : 10;
    }

    /**
     * @return array{tamaño_bytes: int|null, archivo: array{nombre_original: string, extension: string|null, mime: string|null}|null}
     */
    private function resolvePrimaryFileMeta(Document $document): array
    {
        $entry = $this->primaryFileEntry($document);
        if ($entry === null) {
            return ['tamaño_bytes' => null, 'archivo' => null];
        }

        $original = $entry['original_name'];
        $extension = pathinfo($original, PATHINFO_EXTENSION);
        $extension = is_string($extension) && $extension !== '' ? strtolower($extension) : null;

        $tamaño = null;
        $mime = null;
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('private');
        try {
            if ($disk->exists($entry['download_link'])) {
                $tamaño = $disk->size($entry['download_link']);
                try {
                    $mime = $disk->mimeType($entry['download_link']);
                } catch (Throwable) {
                    $mime = null;
                }
            }
        } catch (Throwable) {
            $tamaño = null;
            $mime = null;
        }

        return [
            'tamaño_bytes' => is_int($tamaño) ? $tamaño : null,
            'archivo' => [
                'nombre_original' => $original,
                'extension' => $extension,
                'mime' => is_string($mime) && $mime !== '' ? $mime : null,
            ],
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeFileEntries(Document $document): array
    {
        $raw = $document->getAttribute('file');
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<string>  $routes
     * @return list<int>
     */
    private function typeIdsForRoutes(array $routes): array
    {
        if ($routes === []) {
            return [];
        }

        return IndicatorType::query()
            ->whereIn('route', $routes)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function normalizedLey(?string $ley): ?string
    {
        if ($ley === null || $ley === '') {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $ley) ?? '';

        return array_key_exists($normalized, self::LEY_ROUTES) ? $normalized : null;
    }

    private function normalizedInstrumento(?string $instrumento): ?string
    {
        if ($instrumento === null || $instrumento === '') {
            return null;
        }

        $normalized = strtolower(trim($instrumento));

        return in_array($normalized, self::INSTRUMENTOS, true) ? $normalized : null;
    }

    private function leyFromRoute(?string $route): ?string
    {
        if ($route === null) {
            return null;
        }

        foreach (self::LEY_ROUTES as $ley => $slug) {
            if ($route === $slug) {
                return $ley;
            }
        }

        return null;
    }

    private function instrumentoFromRoute(?string $route): ?string
    {
        if ($route === null || $route === '') {
            return null;
        }

        if ($route === 'comges') {
            return 'comges';
        }

        if ($route === 'adp') {
            return 'adp';
        }

        if (str_starts_with($route, 'metas-')) {
            return 'metas';
        }

        return null;
    }
}
