<?php

namespace App\Services\Cen;

use App\Support\Cen\CenServicioSaludCodigo;
use Illuminate\Support\Facades\Http;

final class NationalEstablishmentCatalogClient
{
    /** Campo CKAN: solo establecimientos pertenecientes al SNSS. */
    public const FILTER_TIPO_PERTENENCIA_FIELD = 'TipoPertenenciaEstabGlosa';

    public const FILTER_TIPO_PERTENENCIA_VALUE = 'Perteneciente al Sistema Nacional de Servicios de Salud';

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function fetchRecords(?string $ssCodigo = null): \Generator
    {
        $baseUrl = rtrim((string) config('centinela.national_establishments.api_base'), '/');
        $resourceId = (string) config('centinela.national_establishments.resource_id');
        $pageSize = max(50, (int) config('centinela.national_establishments.page_size', 500));
        $timeout = max(10, (int) config('centinela.national_establishments.timeout_seconds', 60));

        $offset = 0;
        $total = null;

        do {
            $query = [
                'resource_id' => $resourceId,
                'limit' => $pageSize,
                'offset' => $offset,
            ];

            $query['filters'] = json_encode(
                $this->buildApiFilters($ssCodigo),
                JSON_THROW_ON_ERROR,
            );

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get("{$baseUrl}/datastore_search", $query);

            $response->throw();

            /** @var array<string, mixed> $payload */
            $payload = $response->json('result', []);
            $records = $payload['records'] ?? [];

            if (! is_array($records)) {
                break;
            }

            if ($total === null) {
                $total = (int) ($payload['total'] ?? count($records));
            }

            foreach ($records as $record) {
                if (is_array($record)) {
                    yield $record;
                }
            }

            $offset += $pageSize;
        } while ($offset < $total);
    }

    public function isEnabled(): bool
    {
        return (bool) config('centinela.national_establishments.enabled', false);
    }

    /**
     * Filtros CKAN datastore_search (AND). Siempre SNSS perteneciente; SS opcional.
     *
     * @return array<string, string>
     */
    private function buildApiFilters(?string $ssCodigo): array
    {
        $filters = [
            self::FILTER_TIPO_PERTENENCIA_FIELD => self::FILTER_TIPO_PERTENENCIA_VALUE,
        ];

        if ($ssCodigo !== null && $ssCodigo !== '') {
            $filters['SeremiSaludCodigo_ServicioDeSaludCodigo'] = CenServicioSaludCodigo::toApiFilterCodigo($ssCodigo) ?? $ssCodigo;
        }

        return $filters;
    }
}
