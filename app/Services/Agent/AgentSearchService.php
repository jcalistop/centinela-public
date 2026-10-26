<?php

namespace App\Services\Agent;

use App\Models\Year;
use App\Support\Agent\AgentPagination;

final class AgentSearchService
{
    public const MIN_QUERY_LENGTH = 2;

    public function __construct(
        private ComgesReadAdapter $comges,
        private MetasReadAdapter $metas,
    ) {}

    /**
     * @return array{ok: true, payload: array{data: list<array<string, mixed>>, meta: array<string, mixed>}}|array{ok: false, code: string, message: string, status: int, details?: array<string, mixed>}
     */
    public function search(Year $year, ?string $q, mixed $perPageRaw): array
    {
        $q = is_string($q) ? trim($q) : '';
        if (mb_strlen($q) < self::MIN_QUERY_LENGTH) {
            return [
                'ok' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => 'Parámetro q requerido (mínimo '.self::MIN_QUERY_LENGTH.' caracteres).',
                'status' => 422,
                'details' => ['q' => $q],
            ];
        }

        $perPage = AgentPagination::perPage($perPageRaw);
        $perSide = max(1, intdiv($perPage, 2));

        $comgesPage = $this->comges->paginateIndicators($year, $q, $perSide);
        $metasPage = $this->metas->paginateIndicators($year, null, $q, $perSide);

        $comgesItems = collect($comgesPage->items())
            ->map(fn ($i) => $this->comges->mapListItem($i))
            ->values()
            ->all();
        $metasItems = collect($metasPage->items())
            ->map(fn ($i) => $this->metas->mapListItem($i))
            ->values()
            ->all();

        $data = array_slice(array_merge($comgesItems, $metasItems), 0, $perPage);

        return [
            'ok' => true,
            'payload' => [
                'data' => $data,
                'meta' => [
                    'q' => $q,
                    'year' => (int) $year->ano,
                    'comges_returned' => count($comgesItems),
                    'metas_returned' => count($metasItems),
                    'per_page' => $perPage,
                ],
            ],
        ];
    }
}
