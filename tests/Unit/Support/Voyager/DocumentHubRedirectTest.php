<?php

namespace Tests\Unit\Support\Voyager;

use App\Support\Voyager\DocumentHubRedirect;
use Illuminate\Http\Request;
use Tests\TestCase;

class DocumentHubRedirectTest extends TestCase
{
    public function test_hub_url_from_context_for_adp(): void
    {
        $url = DocumentHubRedirect::hubUrlFromContext([
            'hub' => 'adp',
            'indicator_type_id' => 1,
        ]);

        $this->assertIsString($url);
        $this->assertStringContainsString('/admin/adp', $url);
        $this->assertStringContainsString('#tab-documentos', $url);
    }

    public function test_append_hub_query_to_url(): void
    {
        $url = DocumentHubRedirect::appendHubQueryToUrl('/admin/documentos/1/edit', [
            'hub' => 'metas',
            'metas_route' => 'metas-18834',
            'indicator_type_id' => 9,
        ]);

        $this->assertStringContainsString('hub=metas', $url);
        $this->assertStringContainsString('metas_route=metas-18834', $url);
        $this->assertStringContainsString('indicator_type_id=9', $url);
    }

    /**
     * @dataProvider hubPayloadCases
     *
     * @param  array<string, string|null>  $payload
     * @param  list<string>  $needleChecks
     */
    public function test_url_after_store(array $payload, bool $expectsNull, array $needleChecks): void
    {
        $request = Request::create('/', 'POST', $payload);
        $url = DocumentHubRedirect::urlAfterStore($request);

        if ($expectsNull) {
            $this->assertNull($url);

            return;
        }

        $this->assertIsString($url);
        foreach ($needleChecks as $needle) {
            $this->assertStringContainsString($needle, $url);
        }
    }

    /**
     * @return iterable<string, array{0: array<string, string|null>, 1: bool, 2: array<int, string>}>
     */
    public static function hubPayloadCases(): iterable
    {
        yield 'comges' => [
            ['centinela_document_hub' => 'comges'],
            false,
            ['/admin/compromisos', '#documentos'],
        ];

        yield 'adp' => [
            ['centinela_document_hub' => 'adp'],
            false,
            ['/admin/adp', '#tab-documentos'],
        ];

        yield 'metas_sin_ruta' => [
            ['centinela_document_hub' => 'metas'],
            true,
            [],
        ];

        yield 'empty' => [
            [],
            true,
            [],
        ];
    }
}
