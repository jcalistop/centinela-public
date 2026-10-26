<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CentinelaAgentServer;
use App\Mcp\Tools\GetDashboardAlertasTool;
use App\Mcp\Tools\GetGlossaryTool;
use App\Mcp\Tools\GetIndicadorTool;
use App\Mcp\Tools\ListCompromisosTool;
use App\Mcp\Tools\ListIndicadoresTool;
use App\Mcp\Tools\ListInstrumentosTool;
use App\Models\CenHieDependency;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\MetIndicator;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Dashboard\NonAdminDashboardService;
use App\Support\YearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class CentinelaAgentMcpCatalogoSemanticaTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private Year $year;

    private ComCommitment $commitmentEvaluables;

    private ComCommitment $commitmentMonitoreo;

    private ComCommitment $commitmentEliminados;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.agent_api.enabled' => true,
            'centinela.agent_api.ip_allowlist' => [],
        ]);

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        app(YearContext::class)->setCurrent($this->year);

        foreach ([[1, 'SS01', 'Servicio Uno'], [2, 'SS02', 'Servicio Dos']] as [$id, $codigo, $nombre]) {
            CenHieDependency::unguarded(fn () => CenHieDependency::firstOrCreate(
                ['id' => $id],
                [
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'tipo_dependencia' => 'SERVICIO',
                    'habilitado' => true,
                ]
            ));
        }

        $role = Role::firstOrCreate(['name' => 'agente'], ['display_name' => 'Agente']);
        $this->agent = $this->createAgent('mcp-catalogo-ss1@example.com', $role, 1);

        $this->buildCatalog();
    }

    /** @test */
    public function list_instrumentos_reports_only_present_codes(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListInstrumentosTool::class, [])
        );

        $codes = collect($payload['data'])->pluck('code')->all();
        $leyes = collect($payload['data'])->firstWhere('code', 'metas')['leyes'];
        $leyCodes = collect($leyes)->pluck('code')->all();

        $this->assertSame(['comges', 'metas'], $codes);
        $this->assertNotContains('19813', $leyCodes);
        $this->assertNotContains('adp', $codes);
        $this->assertSame(['18834', '19664', '20707'], $leyCodes);
    }

    /** @test */
    public function list_indicadores_marks_absent_law_as_ausente(): void
    {
        foreach (['19813', 'foo'] as $ley) {
            $payload = $this->toolJson(
                CentinelaAgentServer::actingAs($this->agent)
                    ->tool(ListIndicadoresTool::class, [
                        'instrumento' => 'metas',
                        'ley' => $ley,
                        'year' => 2026,
                    ])
            );

            $this->assertSame([], $payload['data']);
            $this->assertSame('ausente', $payload['meta']['empty_reason']);
            $this->assertSame($ley, $payload['meta']['ley']);
            $this->assertSame(0, $payload['meta']['total']);
        }
    }

    /** @test */
    public function list_indicadores_marks_absent_instrument_as_ausente(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListIndicadoresTool::class, [
                    'instrumento' => 'adp',
                    'year' => 2026,
                ])
        );

        $this->assertSame([], $payload['data']);
        $this->assertSame('ausente', $payload['meta']['empty_reason']);
        $this->assertSame('adp', $payload['meta']['instrumento']);
        $this->assertArrayNotHasKey('error', $payload);
    }

    /** @test */
    public function get_indicador_marks_absent_instrument_as_ausente_json(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetIndicadorTool::class, [
                    'instrumento' => 'bar',
                    'id' => 999,
                    'year' => 2026,
                ])
        );

        $this->assertNull($payload['data']);
        $this->assertSame('ausente', $payload['meta']['empty_reason']);
        $this->assertSame('bar', $payload['meta']['instrumento']);
        $this->assertArrayNotHasKey('error', $payload);
    }

    /** @test */
    public function comges_detail_and_list_serialize_the_catalog_exactly(): void
    {
        $detail = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetIndicadorTool::class, [
                    'instrumento' => 'comges',
                    'id' => $this->indicatorWithTypo()->id,
                    'year' => 2026,
                ])
        )['data'];

        $list = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListIndicadoresTool::class, [
                    'instrumento' => 'comges',
                    'year' => 2026,
                    'q' => 'Inidcador',
                ])
        );

        $this->assertSame('Inidcador de monitoreo 11', $detail['clave']);
        $this->assertSame('Inidcador de monitoreo 11', $list['data'][0]['clave']);
        $this->assertSame('Clave con typo', $detail['nombre']);
        $this->assertSame('COMGES 1', $detail['compromiso_clave']);
        $this->assertSame($this->commitmentEvaluables->id, (int) $detail['compromiso_id']);

        foreach (['fuente_estado', 'version_oott', 'estado_vigente'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $detail);
        }

        $this->assertSame([], $detail['cutoffs']);
    }

    /** @test */
    public function get_dashboard_alertas_reports_evaluation_freshness(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetDashboardAlertasTool::class, ['year' => 2026])
        );

        $this->assertIsArray($payload['data']);
        $this->assertSame(0, $payload['meta']['count']);
        $this->assertNotNull($payload['meta']['last_evaluated_at']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $payload['meta']['last_evaluated_at']
        );
        $this->assertGreaterThanOrEqual(1, $payload['meta']['reglas_evaluadas']);

        $invalid = app(NonAdminDashboardService::class)->alertsEvaluationFor($this->agent, $this->year, 0);
        $this->assertNull($invalid['last_evaluated_at']);
        $this->assertSame(0, $invalid['reglas_evaluadas']);
    }

    /** @test */
    public function dashboard_alertas_rest_has_parity(): void
    {
        Sanctum::actingAs($this->agent, ['read']);

        $this->getJson('/api/v1/dashboard/alertas?year=2026')
            ->assertOk()
            ->assertJsonPath('meta.count', 0)
            ->assertJsonStructure([
                'data',
                'meta' => ['year', 'cen_hie_dependency_id', 'count', 'last_evaluated_at', 'reglas_evaluadas'],
            ]);
    }

    /** @test */
    public function list_compromisos_exposes_opaque_sections_with_indicator_count(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListCompromisosTool::class, ['year' => 2026])
        );

        $this->assertSame(3, $payload['meta']['total']);

        $byClave = collect($payload['data'])->keyBy('clave');
        $this->assertSame(['COMGES 1', 'COMGES Monitoreo', 'COMGES ELIMINADOS'], $byClave->keys()->all());
        $this->assertSame(2, $byClave['COMGES 1']['indicadores_count']);
        $this->assertSame(0, $byClave['COMGES Monitoreo']['indicadores_count']);
        $this->assertSame(1, $byClave['COMGES ELIMINADOS']['indicadores_count']);
        $this->assertSame('Zona sin nombre evaluable', $byClave['COMGES Monitoreo']['nombre']);
    }

    /** @test */
    public function glossary_documents_ausente_sin_datos_and_opaque_sections(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetGlossaryTool::class, [])
        );

        $entries = collect($payload['entries'])->keyBy('term');

        $this->assertTrue($entries->has('ausente'));
        $this->assertTrue($entries->has('sin_datos'));
        $this->assertTrue($entries->has('sección COMGES'));
        $this->assertTrue($entries->has('catálogo tal cual'));
        $this->assertStringContainsString('ausente', $entries['Metas']['definition']);
    }

    /** @test */
    public function present_law_without_rows_is_sin_datos_not_ausente(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListIndicadoresTool::class, [
                    'instrumento' => 'metas',
                    'ley' => '20707',
                    'year' => 2026,
                ])
        );

        $this->assertSame([], $payload['data']);
        $this->assertSame('sin_datos', $payload['meta']['empty_reason']);
        $this->assertSame(0, $payload['meta']['total']);
    }

    /** @test */
    public function absent_law_error_contract_replaces_validation_error_in_rest(): void
    {
        Sanctum::actingAs($this->agent, ['read']);

        $this->getJson('/api/v1/indicadores?instrumento=metas&ley=19813&year=2026')
            ->assertOk()
            ->assertJsonPath('meta.empty_reason', 'ausente')
            ->assertJsonPath('meta.ley', '19813')
            ->assertJsonPath('data', []);

        $this->getJson('/api/v1/indicadores?instrumento=adp&year=2026')
            ->assertOk()
            ->assertJsonPath('meta.empty_reason', 'ausente')
            ->assertJsonPath('meta.instrumento', 'adp');

        $this->getJson('/api/v1/instrumentos')
            ->assertOk()
            ->assertJsonMissing(['code' => '19813']);
    }

    /** @test */
    public function unknown_year_is_still_an_error_not_ausente(): void
    {
        $response = CentinelaAgentServer::actingAs($this->agent)
            ->tool(ListIndicadoresTool::class, [
                'instrumento' => 'metas',
                'ley' => '19813',
                'year' => 1999,
            ]);

        $response->assertHasErrors();
        $reflection = new ReflectionMethod($response, 'content');
        $this->assertStringNotContainsString('ausente', json_encode($reflection->invoke($response)));
    }

    /** @test */
    public function catalog_tools_are_readonly_and_do_not_mutate_catalog(): void
    {
        foreach ([ListInstrumentosTool::class, ListIndicadoresTool::class, GetIndicadorTool::class, ListCompromisosTool::class, GetDashboardAlertasTool::class, GetGlossaryTool::class] as $tool) {
            $ref = new ReflectionClass($tool);
            $this->assertNotEmpty($ref->getAttributes(IsReadOnly::class), $tool.' sin #IsReadOnly');
        }

        $commitmentsBefore = DB::table('com_commitments')->orderBy('id')->get()->toArray();
        $indicatorsBefore = DB::table('com_indicators')->orderBy('id')->get()->toArray();

        $this->toolJson(CentinelaAgentServer::actingAs($this->agent)->tool(ListCompromisosTool::class, ['year' => 2026]));
        $this->toolJson(CentinelaAgentServer::actingAs($this->agent)->tool(GetIndicadorTool::class, [
            'instrumento' => 'comges',
            'id' => $this->indicatorWithTypo()->id,
            'year' => 2026,
        ]));

        $this->assertEquals($commitmentsBefore, DB::table('com_commitments')->orderBy('id')->get()->toArray());
        $this->assertEquals($indicatorsBefore, DB::table('com_indicators')->orderBy('id')->get()->toArray());
        $this->assertSame(
            'Inidcador de monitoreo 11',
            ComIndicator::query()->whereKey($this->indicatorWithTypo()->id)->value('clave')
        );
    }

    private function createAgent(string $email, Role $role, int $ssId): User
    {
        $user = User::factory()->create([
            'name' => 'Agente catálogo',
            'email' => $email,
            'role_id' => $role->id,
        ]);
        $user->forceFill(['cen_hie_dependency_id' => $ssId, 'role_id' => $role->id])->save();
        $user->role()->associate($role)->save();

        return $user;
    }

    private function buildCatalog(): void
    {
        $typeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'COMGES-CAT',
            'nombre_indicador' => 'COMGES catálogo',
            'route' => 'compromisos-gestion',
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->commitmentEvaluables = ComCommitment::unguarded(fn () => ComCommitment::create([
            'indicator_type_id' => $typeId,
            'clave' => 'COMGES 1',
            'nombre' => 'Sección evaluable',
            'orden' => 1,
            'year_id' => $this->year->id,
        ]));

        $this->commitmentMonitoreo = ComCommitment::unguarded(fn () => ComCommitment::create([
            'indicator_type_id' => $typeId,
            'clave' => 'COMGES Monitoreo',
            'nombre' => 'Zona sin nombre evaluable',
            'orden' => 2,
            'year_id' => $this->year->id,
        ]));

        $this->commitmentEliminados = ComCommitment::unguarded(fn () => ComCommitment::create([
            'indicator_type_id' => $typeId,
            'clave' => 'COMGES ELIMINADOS',
            'nombre' => 'Otra etiqueta opaca',
            'orden' => 3,
            'year_id' => $this->year->id,
        ]));

        ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $this->commitmentEvaluables->id,
            'clave' => 'Inidcador de monitoreo 11',
            'nombre' => 'Clave con typo',
            'ponderacion_indicador' => 50,
            'orden' => 1,
        ]));

        ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $this->commitmentEvaluables->id,
            'clave' => 'Indicador con espacios   raros',
            'nombre' => 'Nombre  con   espacios',
            'ponderacion_indicador' => 50,
            'orden' => 2,
        ]));

        ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $this->commitmentEliminados->id,
            'clave' => 'Ex COMGES 6.3 (Eliminado de OOTT)',
            'nombre' => 'Fila tal cual catálogo',
            'ponderacion_indicador' => 1,
            'orden' => 3,
        ]));

        foreach (['18834', '19664', '20707'] as $index => $ley) {
            DB::table('indicator_types')->insert([
                'clave_indicador' => 'METAS-'.$ley.'-CAT',
                'nombre_indicador' => 'Metas '.$ley,
                'route' => 'metas-'.$ley,
                'estado' => 1,
                'orden' => 10 + $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $type18834 = (int) DB::table('indicator_types')->where('route', 'metas-18834')->value('id');
        MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $type18834,
            'clave' => 'META-CAT-1',
            'nombre' => 'Indicador Metas catálogo',
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));
    }

    private function indicatorWithTypo(): ComIndicator
    {
        return ComIndicator::query()->where('clave', 'Inidcador de monitoreo 11')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function toolJson(TestResponse $response): array
    {
        $response->assertOk();
        $method = new ReflectionMethod($response, 'content');
        $texts = $method->invoke($response);
        $this->assertIsArray($texts);
        $this->assertNotEmpty($texts);
        $decoded = json_decode((string) $texts[0], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
