<?php

namespace Tests\Feature\Mcp;

use App\Enums\Cen\CenCenterType;
use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Mcp\Prompts\OrientatePrompt;
use App\Mcp\Resources\AgentPlaybookResource;
use App\Mcp\Resources\GlossaryResource;
use App\Mcp\Resources\OpenApiResource;
use App\Mcp\Servers\CentinelaAgentServer;
use App\Mcp\Tools\GetCapabilitiesTool;
use App\Mcp\Tools\GetContextTool;
use App\Mcp\Tools\GetEstablecimientoTool;
use App\Mcp\Tools\GetGlossaryTool;
use App\Mcp\Tools\GetHealthTool;
use App\Mcp\Tools\GetOpenApiTool;
use App\Mcp\Tools\ListEstablecimientosTool;
use App\Mcp\Tools\ListIndicadoresTool;
use App\Mcp\Tools\ListInstrumentosTool;
use App\Mcp\Tools\SearchIndicadoresTool;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use ReflectionClass;
use Tests\TestCase;

class CentinelaAgentMcpTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.agent_api.enabled' => true,
            'centinela.agent_api.ip_allowlist' => [],
        ]);

        Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::firstOrCreate(
            ['id' => 1],
            [
                'codigo' => 'SS01',
                'nombre' => 'Servicio Uno',
                'tipo_dependencia' => 'SERVICIO',
                'habilitado' => true,
            ]
        ));

        $role = Role::firstOrCreate(['name' => 'agente'], ['display_name' => 'Agente']);
        $this->agent = User::factory()->create([
            'email' => 'mcp-agente@example.com',
            'role_id' => $role->id,
        ]);
        $this->agent->forceFill(['cen_hie_dependency_id' => 1, 'role_id' => $role->id])->save();
        $this->agent->role()->associate($role)->save();
    }

    /** @test */
    public function all_registered_tools_are_readonly(): void
    {
        $server = new CentinelaAgentServer(new FakeTransporter);
        $context = $server->createContext();

        foreach ($context->tools() as $tool) {
            $ref = new ReflectionClass($tool);
            $attrs = $ref->getAttributes(IsReadOnly::class);
            $this->assertNotEmpty(
                $attrs,
                $tool::class.' debe declarar #[IsReadOnly]'
            );
        }
    }

    /** @test */
    public function health_and_context_tools_require_authenticated_user(): void
    {
        CentinelaAgentServer::tool(GetHealthTool::class, [])
            ->assertHasErrors();

        CentinelaAgentServer::actingAs($this->agent)
            ->tool(GetHealthTool::class, [])
            ->assertOk()
            ->assertSee('mcp');
    }

    /** @test */
    public function read_tools_return_neutral_payloads(): void
    {
        CentinelaAgentServer::actingAs($this->agent)
            ->tool(ListInstrumentosTool::class, [])
            ->assertOk()
            ->assertSee('comges')
            ->assertSee('metas');

        CentinelaAgentServer::actingAs($this->agent)
            ->tool(GetContextTool::class, ['year' => 2026])
            ->assertOk()
            ->assertSee('cen_hie_dependency_id');

        CentinelaAgentServer::actingAs($this->agent)
            ->tool(GetGlossaryTool::class, [])
            ->assertOk()
            ->assertSee('instrumento');
    }

    /** @test */
    public function list_indicadores_requires_instrumento(): void
    {
        CentinelaAgentServer::actingAs($this->agent)
            ->tool(ListIndicadoresTool::class, [])
            ->assertHasErrors();

        CentinelaAgentServer::actingAs($this->agent)
            ->tool(ListIndicadoresTool::class, ['instrumento' => 'comges'])
            ->assertOk();
    }

    /** @test */
    public function search_indicadores_is_registered_readonly_and_requires_q(): void
    {
        $server = new CentinelaAgentServer(new FakeTransporter);
        $classes = $server->createContext()->tools()->map(fn (Tool $tool): string => $tool::class)->all();
        $this->assertContains(SearchIndicadoresTool::class, $classes);

        $ref = new ReflectionClass(SearchIndicadoresTool::class);
        $this->assertNotEmpty($ref->getAttributes(IsReadOnly::class));

        CentinelaAgentServer::tool(SearchIndicadoresTool::class, ['q' => 'lista'])
            ->assertHasErrors();

        CentinelaAgentServer::actingAs($this->agent)
            ->tool(SearchIndicadoresTool::class, ['q' => 'a'])
            ->assertHasErrors();

        CentinelaAgentServer::actingAs($this->agent)
            ->tool(SearchIndicadoresTool::class, ['q' => 'lista', 'year' => 2026])
            ->assertOk()
            ->assertSee('meta');
    }

    /** @test */
    public function capabilities_and_openapi_tools_are_readonly_and_registered(): void
    {
        $server = new CentinelaAgentServer(new FakeTransporter);
        $tools = $server->createContext()->tools();
        $classes = $tools->map(fn (Tool $tool): string => $tool::class)->all();

        $this->assertContains(GetCapabilitiesTool::class, $classes);
        $this->assertContains(GetOpenApiTool::class, $classes);

        foreach ([GetCapabilitiesTool::class, GetOpenApiTool::class] as $class) {
            $ref = new ReflectionClass($class);
            $this->assertNotEmpty($ref->getAttributes(IsReadOnly::class), $class.' debe declarar #[IsReadOnly]');
        }
    }

    /** @test */
    public function get_capabilities_requires_auth_and_lists_ritual(): void
    {
        CentinelaAgentServer::tool(GetCapabilitiesTool::class, [])
            ->assertHasErrors();

        CentinelaAgentServer::actingAs($this->agent)
            ->tool(GetCapabilitiesTool::class, [])
            ->assertOk()
            ->assertSee('ritual')
            ->assertSee('excluded')
            ->assertSee('set_context');
    }

    /** @test */
    public function get_openapi_returns_file_payload(): void
    {
        CentinelaAgentServer::actingAs($this->agent)
            ->tool(GetOpenApiTool::class, [])
            ->assertOk()
            ->assertSee('openapi')
            ->assertSee('paths');
    }

    /** @test */
    public function glossary_includes_orientation_terms(): void
    {
        $path = base_path('docs/api/v1/glossary.json');
        $this->assertTrue(File::isFile($path));
        $raw = File::get($path);
        $this->assertStringContainsString('etapa_vigente', $raw);
        $this->assertStringContainsString('PII/egreso', $raw);

        CentinelaAgentServer::actingAs($this->agent)
            ->tool(GetGlossaryTool::class, [])
            ->assertOk()
            ->assertSee('etapa_vigente')
            ->assertSee('egreso');
    }

    /** @test */
    public function no_set_context_tool_is_registered(): void
    {
        $server = new CentinelaAgentServer(new FakeTransporter);
        $names = $server->createContext()->tools()->map(
            fn (Tool $tool): string => $tool->name()
        )->all();

        foreach ($names as $name) {
            $this->assertStringNotContainsString('set_context', strtolower($name));
            $this->assertStringNotContainsString('set-context', strtolower($name));
        }
    }

    /** @test */
    public function http_mcp_rejects_when_flag_off(): void
    {
        config(['centinela.agent_api.enabled' => false]);
        $token = $this->agent->createToken('mcp', ['read'], now()->addHour())->plainTextToken;

        $this->withToken($token)
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => new \stdClass,
                    'clientInfo' => ['name' => 'test', 'version' => '1.0'],
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AGENT_API_DISABLED');
    }

    /** @test */
    public function http_mcp_rejects_unauthenticated(): void
    {
        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'ping',
            'params' => new \stdClass,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /** @test */
    public function agent_issue_token_never_grants_write_ability(): void
    {
        $this->artisan('centinela:agent:issue-token', [
            'email' => 'mcp-write-check@example.com',
            '--create' => true,
            '--ss' => 1,
            '--ttl' => 30,
        ])->assertExitCode(0);

        $user = User::query()->where('email', 'mcp-write-check@example.com')->first();
        $this->assertNotNull($user);
        $abilities = $user->tokens()->first()?->abilities ?? [];
        $this->assertSame(['read'], $abilities);
        $this->assertFalse(in_array('write', $abilities, true));
        $this->assertFalse(in_array('write:*', $abilities, true));
    }

    /** @test */
    public function resources_registered_with_stable_uris(): void
    {
        $server = new CentinelaAgentServer(new FakeTransporter);
        $uris = $server->createContext()->resources()->map(
            fn (Resource $resource): string => $resource->uri()
        )->all();

        $this->assertContains('centinela://v1/glossary', $uris);
        $this->assertContains('centinela://v1/openapi', $uris);
        $this->assertContains('centinela://v1/agent-playbook', $uris);
    }

    /** @test */
    public function resources_require_auth(): void
    {
        CentinelaAgentServer::resource(GlossaryResource::class)
            ->assertHasErrors();

        CentinelaAgentServer::resource(OpenApiResource::class)
            ->assertHasErrors();

        CentinelaAgentServer::resource(AgentPlaybookResource::class)
            ->assertHasErrors();
    }

    /** @test */
    public function glossary_resource_matches_file(): void
    {
        CentinelaAgentServer::actingAs($this->agent)
            ->resource(GlossaryResource::class)
            ->assertOk()
            ->assertSee('instrumento')
            ->assertSee('etapa_vigente');
    }

    /** @test */
    public function openapi_and_playbook_resources_return_canonical_files(): void
    {
        CentinelaAgentServer::actingAs($this->agent)
            ->resource(OpenApiResource::class)
            ->assertOk()
            ->assertSee('openapi')
            ->assertSee('paths');

        CentinelaAgentServer::actingAs($this->agent)
            ->resource(AgentPlaybookResource::class)
            ->assertOk()
            ->assertSee('playbook agente P3a')
            ->assertSee('set_context')
            ->assertSee('PII/egreso');
    }

    /** @test */
    public function orientate_prompt_is_registered(): void
    {
        $server = new CentinelaAgentServer(new FakeTransporter);
        $names = $server->createContext()->prompts()->map(
            fn (Prompt $prompt): string => $prompt->name()
        )->all();

        $this->assertContains('orientate', $names);

        CentinelaAgentServer::prompt(OrientatePrompt::class)
            ->assertHasErrors();

        CentinelaAgentServer::actingAs($this->agent)
            ->prompt(OrientatePrompt::class)
            ->assertOk()
            ->assertSee('playbook')
            ->assertSee('glossary')
            ->assertSee('get-capabilities-tool');
    }

    /** @test */
    public function no_write_surface_introduced(): void
    {
        $server = new CentinelaAgentServer(new FakeTransporter);
        $context = $server->createContext();

        foreach ($context->tools() as $tool) {
            $ref = new ReflectionClass($tool);
            $this->assertNotEmpty(
                $ref->getAttributes(IsReadOnly::class),
                $tool::class.' debe declarar #[IsReadOnly]'
            );
        }

        $toolNames = $context->tools()->map(fn (Tool $tool): string => strtolower($tool->name()))->all();
        foreach ($toolNames as $name) {
            $this->assertStringNotContainsString('set_context', $name);
            $this->assertStringNotContainsString('write', $name);
        }

        $promptNames = $context->prompts()->map(fn (Prompt $prompt): string => strtolower($prompt->name()))->all();
        foreach ($promptNames as $name) {
            $this->assertStringNotContainsString('write', $name);
            $this->assertStringNotContainsString('set_context', $name);
        }
    }

    /** @test */
    public function establecimientos_tools_are_readonly_and_filter_sar_vigente(): void
    {
        $server = new CentinelaAgentServer(new FakeTransporter);
        $classes = $server->createContext()->tools()->map(fn (Tool $tool): string => $tool::class)->all();
        $this->assertContains(ListEstablecimientosTool::class, $classes);
        $this->assertContains(GetEstablecimientoTool::class, $classes);

        CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => 'MCP-SAR',
            'nombre_oficial' => 'SAR MCP',
            'nombre_corto' => 'SAR MCP',
            'cen_hie_dependency_id' => 1,
            'pertenencia_snss' => 'Perteneciente',
            'cen_type' => CenCenterType::Case45->value,
            'cen_scope' => 'Establecimiento de Salud',
            'cen_certification' => 'No Aplica',
            'cen_adm_dependency' => 'Municipal',
            'nivel_atencion' => 'Primario',
            'tiene_urgencia' => 'No',
            'cen_emergency_type' => 'No Aplica',
            'tipo_sapu' => 'No Aplica',
            'cen_provider_type' => 'Público',
            'estado_funcionamiento' => CentroEstadoFuncionamiento::VigenteEnOperacionHabitual->value,
            'nivel_complejidad' => 'Baja',
            'tipo_atencion' => 'Abierta',
        ]));

        CentinelaAgentServer::tool(ListEstablecimientosTool::class, [])
            ->assertHasErrors();

        CentinelaAgentServer::actingAs($this->agent)
            ->tool(ListEstablecimientosTool::class, [
                'tipo' => 'SAR',
                'vigente' => true,
            ])
            ->assertOk()
            ->assertSee('MCP-SAR')
            ->assertSee('total');

        $id = (int) CenCenter::query()->where('codigo', 'MCP-SAR')->value('id');
        CentinelaAgentServer::actingAs($this->agent)
            ->tool(GetEstablecimientoTool::class, ['id' => $id])
            ->assertOk()
            ->assertSee('updated_at')
            ->assertSee('MCP-SAR');
    }
}
