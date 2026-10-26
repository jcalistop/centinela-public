<?php

namespace Tests\Unit\Services\Cen;

use App\Services\Cen\NationalEstablishmentCatalogClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NationalEstablishmentCatalogClientTest extends TestCase
{
    public function test_fetch_records_always_filters_by_snss_pertenencia(): void
    {
        Config::set('centinela.national_establishments.api_base', 'https://datos.gob.cl/api/3/action');
        Config::set('centinela.national_establishments.resource_id', 'test-resource');
        Config::set('centinela.national_establishments.page_size', 50);

        Http::fake([
            '*/datastore_search*' => Http::response([
                'success' => true,
                'result' => [
                    'total' => 0,
                    'records' => [],
                ],
            ]),
        ]);

        $client = new NationalEstablishmentCatalogClient;
        iterator_to_array($client->fetchRecords(null));

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (! isset($query['filters'])) {
                return false;
            }

            $filters = json_decode($query['filters'], true, 512, JSON_THROW_ON_ERROR);

            return ($filters[NationalEstablishmentCatalogClient::FILTER_TIPO_PERTENENCIA_FIELD] ?? null)
                === NationalEstablishmentCatalogClient::FILTER_TIPO_PERTENENCIA_VALUE
                && ! isset($filters['SeremiSaludCodigo_ServicioDeSaludCodigo']);
        });
    }

    public function test_fetch_records_combines_snss_filter_with_ss_codigo(): void
    {
        Config::set('centinela.national_establishments.api_base', 'https://datos.gob.cl/api/3/action');
        Config::set('centinela.national_establishments.resource_id', 'test-resource');
        Config::set('centinela.national_establishments.page_size', 50);

        Http::fake([
            '*/datastore_search*' => Http::response([
                'success' => true,
                'result' => [
                    'total' => 0,
                    'records' => [],
                ],
            ]),
        ]);

        $client = new NationalEstablishmentCatalogClient;
        iterator_to_array($client->fetchRecords('24'));

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (! isset($query['filters'])) {
                return false;
            }

            $filters = json_decode($query['filters'], true, 512, JSON_THROW_ON_ERROR);

            return ($filters[NationalEstablishmentCatalogClient::FILTER_TIPO_PERTENENCIA_FIELD] ?? null)
                === NationalEstablishmentCatalogClient::FILTER_TIPO_PERTENENCIA_VALUE
                && ($filters['SeremiSaludCodigo_ServicioDeSaludCodigo'] ?? null) === '24';
        });
    }

    public function test_fetch_records_zero_pads_single_digit_ss_codigo_for_api_filter(): void
    {
        Config::set('centinela.national_establishments.api_base', 'https://datos.gob.cl/api/3/action');
        Config::set('centinela.national_establishments.resource_id', 'test-resource');
        Config::set('centinela.national_establishments.page_size', 50);

        Http::fake([
            '*/datastore_search*' => Http::response([
                'success' => true,
                'result' => [
                    'total' => 0,
                    'records' => [],
                ],
            ]),
        ]);

        $client = new NationalEstablishmentCatalogClient;
        iterator_to_array($client->fetchRecords('5'));

        Http::assertSent(function ($request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (! isset($query['filters'])) {
                return false;
            }

            $filters = json_decode($query['filters'], true, 512, JSON_THROW_ON_ERROR);

            return ($filters['SeremiSaludCodigo_ServicioDeSaludCodigo'] ?? null) === '05';
        });
    }
}
