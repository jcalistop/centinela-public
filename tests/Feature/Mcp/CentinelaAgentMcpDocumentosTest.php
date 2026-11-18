<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CentinelaAgentServer;
use App\Mcp\Tools\GetCapabilitiesTool;
use App\Mcp\Tools\GetDocumentoTool;
use App\Mcp\Tools\GetGlossaryTool;
use App\Mcp\Tools\ListDocumentosTool;
use App\Models\CenHieDependency;
use App\Models\Document;
use App\Models\IndicatorType;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Mcp\Server\Testing\TestResponse;
use ReflectionMethod;
use Tests\TestCase;

class CentinelaAgentMcpDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $agentOtherSs;

    private User $visualizador;

    private Year $year2026;

    private IndicatorType $type18834;

    private IndicatorType $typeComges;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.agent_api.enabled' => true,
            'centinela.agent_api.ip_allowlist' => [],
            'centinela.agent_document_url_ttl_minutes' => 10,
        ]);

        Storage::fake('private');

        $this->year2026 = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::firstOrCreate(
            ['id' => 1],
            [
                'codigo' => 'SS01',
                'nombre' => 'Servicio Uno',
                'tipo_dependencia' => 'SERVICIO',
                'habilitado' => true,
            ]
        ));
        CenHieDependency::unguarded(fn () => CenHieDependency::firstOrCreate(
            ['id' => 2],
            [
                'codigo' => 'SS02',
                'nombre' => 'Servicio Dos',
                'tipo_dependencia' => 'SERVICIO',
                'habilitado' => true,
            ]
        ));

        $roleAgente = Role::firstOrCreate(['name' => 'agente'], ['display_name' => 'Agente']);
        $roleVis = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);

        $this->agent = User::factory()->create([
            'email' => 'mcp-docs@example.com',
            'name' => 'Agente Docs',
            'role_id' => $roleAgente->id,
        ]);
        $this->agent->forceFill(['cen_hie_dependency_id' => 1, 'role_id' => $roleAgente->id])->save();
        $this->agent->role()->associate($roleAgente)->save();

        $this->agentOtherSs = User::factory()->create([
            'email' => 'mcp-docs-ss2@example.com',
            'role_id' => $roleAgente->id,
        ]);
        $this->agentOtherSs->forceFill(['cen_hie_dependency_id' => 2, 'role_id' => $roleAgente->id])->save();
        $this->agentOtherSs->role()->associate($roleAgente)->save();

        $this->visualizador = User::factory()->create([
            'email' => 'mcp-docs-vis@example.com',
            'name' => 'Visualizador Docs',
            'role_id' => $roleVis->id,
        ]);
        $this->visualizador->forceFill(['cen_hie_dependency_id' => 1, 'role_id' => $roleVis->id])->save();
        $this->visualizador->role()->associate($roleVis)->save();

        $this->type18834 = IndicatorType::query()->firstOrCreate(
            ['route' => 'metas-18834'],
            [
                'clave_indicador' => 'M18834',
                'nombre_indicador' => 'Metas 18.834',
                'estado' => true,
                'orden' => 1,
            ]
        );

        $this->typeComges = IndicatorType::query()->firstOrCreate(
            ['route' => 'comges'],
            [
                'clave_indicador' => 'comges',
                'nombre_indicador' => 'COMGES',
                'estado' => true,
                'orden' => 2,
            ]
        );
    }

    /** @test */
    public function list_documentos_appears_in_capabilities_and_returns_row(): void
    {
        $caps = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetCapabilitiesTool::class, [])
                ->assertOk()
        );
        $names = collect($caps['tools'])->pluck('name')->all();
        $this->assertContains('list-documentos-tool', $names);
        $this->assertContains('get-documento-tool', $names);
        $this->assertTrue(
            collect($caps['ritual'])->contains(
                fn ($step) => is_string($step) && str_contains($step, 'list-documentos-tool')
            )
        );

        $doc = $this->createDocument([
            'nombre' => 'Normativa A',
            'indicator_type_id' => $this->type18834->id,
            'relative_path' => 'documents/normativa-a.pdf',
            'original_name' => 'NormativaA.pdf',
            'contents' => 'contenido-a',
        ]);

        Session::forget('indicator_year');
        $beforeSession = Session::get('indicator_year');

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListDocumentosTool::class, [])
                ->assertOk()
        );

        $this->assertNull($beforeSession);
        $this->assertNull(Session::get('indicator_year'));
        $this->assertCount(1, $payload['data']);
        $row = $payload['data'][0];
        $this->assertSame($doc->id, (int) $row['id']);
        $this->assertSame('Normativa A', $row['nombre']);
        $this->assertSame('18834', $row['ley']);
        $this->assertSame('metas', $row['instrumento']);
        $this->assertSame($this->type18834->fresh()->clave_indicador, $row['tipo']['clave']);
        $this->assertSame(11, (int) $row['tamaño_bytes']);
        $this->assertNull($row['hash']);
        $this->assertSame('NormativaA.pdf', $row['archivo']['nombre_original']);
        $this->assertSame('pdf', $row['archivo']['extension']);
        $this->assertArrayNotHasKey('download_link', $row['archivo']);
        $this->assertSame($this->agent->id, (int) $row['usuario']['id']);
        $this->assertSame('Agente Docs', $row['usuario']['nombre']);
        $this->assertArrayNotHasKey('email', $row['usuario']);
        $this->assertStringNotContainsString('documents/normativa-a.pdf', json_encode($row));
    }

    /** @test */
    public function list_filters_tenancy_ley_ausente_and_sin_datos(): void
    {
        $this->createDocument([
            'nombre' => 'Doc SS1',
            'indicator_type_id' => $this->type18834->id,
            'relative_path' => 'documents/ss1.pdf',
            'original_name' => 'ss1.pdf',
            'contents' => 'ss1',
        ]);
        $this->createDocument([
            'nombre' => 'Doc SS2',
            'cen_hie_dependency_id' => 2,
            'user_id' => $this->agentOtherSs->id,
            'indicator_type_id' => $this->type18834->id,
            'relative_path' => 'documents/ss2.pdf',
            'original_name' => 'ss2.pdf',
            'contents' => 'ss2',
        ]);

        $ss1 = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListDocumentosTool::class, ['year' => 2026])
                ->assertOk()
        );
        $this->assertCount(1, $ss1['data']);
        $this->assertSame('Doc SS1', $ss1['data'][0]['nombre']);

        $ausente = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListDocumentosTool::class, ['ley' => '19813'])
                ->assertOk()
        );
        $this->assertSame([], $ausente['data']);
        $this->assertSame('ausente', $ausente['meta']['empty_reason']);

        $sinDatos = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListDocumentosTool::class, ['instrumento' => 'comges'])
                ->assertOk()
        );
        $this->assertSame([], $sinDatos['data']);
        $this->assertSame('sin_datos', $sinDatos['meta']['empty_reason']);
    }

    /** @test */
    public function list_missing_file_returns_null_size_without_breaking_page(): void
    {
        $this->createDocument([
            'nombre' => 'Sin disco',
            'indicator_type_id' => $this->type18834->id,
            'relative_path' => 'documents/missing.pdf',
            'original_name' => 'missing.pdf',
            'contents' => null,
        ]);

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListDocumentosTool::class, ['year' => 2026])
                ->assertOk()
        );

        $this->assertCount(1, $payload['data']);
        $this->assertNull($payload['data'][0]['tamaño_bytes']);
        $this->assertSame('missing.pdf', $payload['data'][0]['archivo']['nombre_original']);
    }

    /** @test */
    public function get_documento_returns_signed_url_and_not_found_cross_ss(): void
    {
        $doc = $this->createDocument([
            'nombre' => 'Para firmar',
            'indicator_type_id' => $this->type18834->id,
            'relative_path' => 'documents/firmar.pdf',
            'original_name' => 'firmar.pdf',
            'contents' => 'payload',
        ]);

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetDocumentoTool::class, ['id' => $doc->id])
                ->assertOk()
        );

        $this->assertSame($doc->id, (int) $payload['id']);
        $this->assertNotEmpty($payload['download_url']);
        $this->assertNotEmpty($payload['expires_at']);
        $this->assertTrue(URL::hasValidSignature(
            request()->create($payload['download_url'], 'GET')
        ));

        $foreign = $this->createDocument([
            'nombre' => 'Otro SS',
            'cen_hie_dependency_id' => 2,
            'user_id' => $this->agentOtherSs->id,
            'indicator_type_id' => $this->type18834->id,
            'relative_path' => 'documents/otro.pdf',
            'original_name' => 'otro.pdf',
            'contents' => 'x',
        ]);

        $missing = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetDocumentoTool::class, ['id' => $foreign->id])
                ->assertOk()
        );
        $this->assertSame('NOT_FOUND', $missing['error']['code']);
        $this->assertArrayNotHasKey('download_url', $missing);
    }

    /** @test */
    public function visualizador_can_list_and_get_document(): void
    {
        $doc = $this->createDocument([
            'nombre' => 'Vis OK',
            'indicator_type_id' => $this->typeComges->id,
            'relative_path' => 'documents/vis.pdf',
            'original_name' => 'vis.pdf',
            'contents' => 'vis',
        ]);

        $list = $this->toolJson(
            CentinelaAgentServer::actingAs($this->visualizador)
                ->tool(ListDocumentosTool::class, ['year' => 2026])
                ->assertOk()
        );
        $this->assertCount(1, $list['data']);

        $get = $this->toolJson(
            CentinelaAgentServer::actingAs($this->visualizador)
                ->tool(GetDocumentoTool::class, ['id' => $doc->id])
                ->assertOk()
        );
        $this->assertNotEmpty($get['download_url']);
    }

    /** @test */
    public function year_validation_and_glossary_mention_documentos(): void
    {
        $badYear = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListDocumentosTool::class, ['year' => 'abc'])
                ->assertOk()
        );
        $this->assertSame('VALIDATION_ERROR', $badYear['error']['code']);
        $this->assertSame('abc', $badYear['error']['details']['year']);

        $missingYear = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListDocumentosTool::class, ['year' => 1999])
                ->assertOk()
        );
        $this->assertSame('NOT_FOUND', $missingYear['error']['code']);

        $glossary = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetGlossaryTool::class, [])
                ->assertOk()
        );
        $terms = collect($glossary['entries'] ?? $glossary['data']['entries'] ?? [])->pluck('term')->all();
        if ($terms === [] && isset($glossary['version'])) {
            $raw = json_decode((string) file_get_contents(base_path('docs/api/v1/glossary.json')), true);
            $terms = collect($raw['entries'] ?? [])->pluck('term')->all();
            $this->assertSame('2026-11-18c', $raw['version']);
        }
        $this->assertContains('documentos', $terms);
        $this->assertContains('hash', $terms);
        $this->assertContains('download_url', $terms);
    }

    /** @test */
    public function token_without_ss_fails(): void
    {
        $role = Role::firstOrCreate(['name' => 'agente'], ['display_name' => 'Agente']);
        $orphan = User::factory()->create([
            'email' => 'mcp-docs-orphan@example.com',
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        // SS inválido (< 1): la columna no admite NULL en el esquema de tests.
        DB::table('users')->where('id', $orphan->id)->update(['cen_hie_dependency_id' => 0]);
        $orphan->refresh();
        $orphan->role()->associate($role)->save();

        CentinelaAgentServer::actingAs($orphan)
            ->tool(ListDocumentosTool::class, [])
            ->assertHasErrors();
    }

    /**
     * @param  array{
     *   nombre?: string,
     *   descripcion?: string,
     *   autor?: string,
     *   indicator_type_id: int,
     *   cen_hie_dependency_id?: int,
     *   user_id?: int,
     *   relative_path: string,
     *   original_name: string,
     *   contents: string|null
     * }  $opts
     */
    private function createDocument(array $opts): Document
    {
        if ($opts['contents'] !== null) {
            Storage::disk('private')->put($opts['relative_path'], $opts['contents']);
        }

        $id = DB::table('documents')->insertGetId([
            'nombre' => $opts['nombre'] ?? 'Documento',
            'descripcion' => $opts['descripcion'] ?? 'Desc',
            'autor' => $opts['autor'] ?? 'Autor libre',
            'file' => json_encode([
                [
                    'download_link' => $opts['relative_path'],
                    'original_name' => $opts['original_name'],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'year_id' => $this->year2026->id,
            'indicator_type_id' => $opts['indicator_type_id'],
            'cen_hie_dependency_id' => $opts['cen_hie_dependency_id'] ?? 1,
            'user_id' => $opts['user_id'] ?? $this->agent->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Document::query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function toolJson(TestResponse $response): array
    {
        $method = new ReflectionMethod($response, 'content');
        $texts = $method->invoke($response);
        $this->assertIsArray($texts);
        $this->assertNotEmpty($texts);
        $decoded = json_decode((string) $texts[0], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
