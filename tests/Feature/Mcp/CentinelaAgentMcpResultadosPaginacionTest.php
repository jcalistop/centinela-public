<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CentinelaAgentServer;
use App\Mcp\Tools\ListEstablecimientosTool;
use App\Mcp\Tools\ListResultadosTool;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Agent\CenCentersReadAdapter;
use App\Support\YearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class CentinelaAgentMcpResultadosPaginacionTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    private User $agent;

    private User $agentOtherSs;

    private Year $year2026;

    private Year $year2025;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.agent_api.enabled' => true,
            'centinela.agent_api.ip_allowlist' => [],
        ]);

        $this->year2026 = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        $this->year2025 = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));

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

        $role = Role::firstOrCreate(['name' => 'agente'], ['display_name' => 'Agente']);
        $this->agent = User::factory()->create([
            'email' => 'mcp-resultados@example.com',
            'role_id' => $role->id,
        ]);
        $this->agent->forceFill(['cen_hie_dependency_id' => 1, 'role_id' => $role->id])->save();
        $this->agent->role()->associate($role)->save();

        $this->agentOtherSs = User::factory()->create([
            'email' => 'mcp-resultados-ss2@example.com',
            'role_id' => $role->id,
        ]);
        $this->agentOtherSs->forceFill(['cen_hie_dependency_id' => 2, 'role_id' => $role->id])->save();
        $this->agentOtherSs->role()->associate($role)->save();

        IndicatorType::query()->firstOrCreate(
            ['route' => 'metas-18834'],
            [
                'clave_indicador' => 'M18834',
                'nombre_indicador' => 'Metas 18.834',
                'estado' => true,
                'orden' => 1,
            ]
        );
    }

    /** @test */
    public function list_resultados_returns_json_for_existing_indicator_with_rows(): void
    {
        $center = $this->createMinimalCenCenter([
            'codigo' => 'RES-A',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'Centro Resultados A',
            'nombre_corto' => 'CRA',
        ]);
        $indicator = $this->createMetIndicator($this->year2026);
        $this->createMetResult($indicator, $center, 1, $this->year2026);

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListResultadosTool::class, [
                    'indicador_id' => $indicator->id,
                    'year' => 2026,
                ])
                ->assertOk()
        );

        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('meta', $payload);
        $this->assertCount(1, $payload['data']);
        $this->assertSame($indicator->id, (int) $payload['data'][0]['indicador_id']);
        $this->assertSame($center->id, (int) $payload['data'][0]['cen_center_id']);
        $this->assertSame(1, (int) $payload['data'][0]['cen_hie_dependency_id']);
        $this->assertSame('Centro Resultados A', $payload['data'][0]['establecimiento']);
        $this->assertArrayHasKey('resultado', $payload['data'][0]);
        $this->assertArrayHasKey('cumple_meta', $payload['data'][0]);
        $this->assertArrayHasKey('periodo', $payload['data'][0]);
        $this->assertArrayHasKey('etapa', $payload['data'][0]);
    }

    /** @test */
    public function list_resultados_returns_empty_list_when_indicator_exists_without_rows(): void
    {
        $indicator = $this->createMetIndicator($this->year2026);

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListResultadosTool::class, [
                    'indicador_id' => $indicator->id,
                    'year' => 2026,
                ])
                ->assertOk()
        );

        $this->assertSame([], $payload['data']);
        $this->assertSame(0, (int) $payload['meta']['total']);
    }

    /** @test */
    public function list_resultados_works_for_year_2025(): void
    {
        $center = $this->createMinimalCenCenter([
            'codigo' => 'RES-2025',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'Centro 2025',
            'nombre_corto' => 'C25',
        ]);
        $indicator = $this->createMetIndicator($this->year2025);
        $this->createMetResult($indicator, $center, 1, $this->year2025);

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListResultadosTool::class, [
                    'indicador_id' => $indicator->id,
                    'year' => 2025,
                ])
                ->assertOk()
        );

        $this->assertCount(1, $payload['data']);
        $this->assertSame($indicator->id, (int) $payload['data'][0]['indicador_id']);
    }

    /** @test */
    public function list_resultados_not_found_is_json_error_not_plain_text(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListResultadosTool::class, [
                    'indicador_id' => 99999,
                    'year' => 2026,
                ])
                ->assertOk()
        );

        $this->assertSame('NOT_FOUND', $payload['error']['code']);
        $this->assertSame('Indicador Metas no encontrado.', $payload['error']['message']);
        $this->assertSame(99999, (int) $payload['error']['details']['indicador_id']);
        $this->assertSame(2026, (int) $payload['error']['details']['year']);
        $this->assertNotEmpty($payload['error']['request_id']);
    }

    /** @test */
    public function list_resultados_invalid_id_is_json_error(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListResultadosTool::class, [
                    'indicador_id' => 0,
                    'year' => 2026,
                ])
                ->assertOk()
        );

        $this->assertSame('VALIDATION_ERROR', $payload['error']['code']);
        $this->assertNotEmpty($payload['error']['request_id']);
    }

    /** @test */
    public function list_resultados_unknown_year_is_json_error(): void
    {
        $indicator = $this->createMetIndicator($this->year2026);

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListResultadosTool::class, [
                    'indicador_id' => $indicator->id,
                    'year' => 1999,
                ])
                ->assertOk()
        );

        $this->assertSame('NOT_FOUND', $payload['error']['code']);
        $this->assertSame(1999, (int) $payload['error']['details']['year']);
        $this->assertNotEmpty($payload['error']['request_id']);
    }

    /** @test */
    public function list_resultados_does_not_leak_other_ss_rows(): void
    {
        $centerA = $this->createMinimalCenCenter([
            'codigo' => 'RES-SS1',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'SS1',
            'nombre_corto' => 'SS1',
        ]);
        $centerB = $this->createMinimalCenCenter([
            'codigo' => 'RES-SS2',
            'cen_hie_dependency_id' => 2,
            'nombre_oficial' => 'SS2',
            'nombre_corto' => 'SS2',
        ]);
        $indicator = $this->createMetIndicator($this->year2026);
        $this->createMetResult($indicator, $centerA, 1, $this->year2026);
        $this->createMetResult($indicator, $centerB, 2, $this->year2026);

        $forA = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListResultadosTool::class, [
                    'indicador_id' => $indicator->id,
                    'year' => 2026,
                ])
                ->assertOk()
        );
        $this->assertCount(1, $forA['data']);
        $this->assertSame(1, (int) $forA['data'][0]['cen_hie_dependency_id']);

        $forB = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agentOtherSs)
                ->tool(ListResultadosTool::class, [
                    'indicador_id' => $indicator->id,
                    'year' => 2026,
                ])
                ->assertOk()
        );
        $this->assertCount(1, $forB['data']);
        $this->assertSame(2, (int) $forB['data'][0]['cen_hie_dependency_id']);
    }

    /** @test */
    public function list_resultados_paginates_by_mcp_page_argument(): void
    {
        $indicator = $this->createMetIndicator($this->year2026);
        for ($i = 1; $i <= 26; $i++) {
            $center = $this->createMinimalCenCenter([
                'codigo' => sprintf('RES-P-%02d', $i),
                'cen_hie_dependency_id' => 1,
                'nombre_oficial' => 'Centro '.$i,
                'nombre_corto' => 'C'.$i,
            ]);
            $this->createMetResult($indicator, $center, 1, $this->year2026);
        }

        $page1 = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListResultadosTool::class, [
                    'indicador_id' => $indicator->id,
                    'year' => 2026,
                    'per_page' => 25,
                    'page' => 1,
                ])
                ->assertOk()
        );
        $page2 = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListResultadosTool::class, [
                    'indicador_id' => $indicator->id,
                    'year' => 2026,
                    'per_page' => 25,
                    'page' => 2,
                ])
                ->assertOk()
        );

        $this->assertSame(1, (int) $page1['meta']['current_page']);
        $this->assertSame(2, (int) $page2['meta']['current_page']);
        $this->assertSame(26, (int) $page1['meta']['total']);
        $this->assertCount(25, $page1['data']);
        $this->assertCount(1, $page2['data']);
        $this->assertNotEquals(
            collect($page1['data'])->pluck('cen_center_id')->all(),
            collect($page2['data'])->pluck('cen_center_id')->all()
        );
    }

    /** @test */
    public function list_establecimientos_page_two_is_distinct_and_covers_total(): void
    {
        for ($i = 1; $i <= 101; $i++) {
            $this->createMinimalCenCenter([
                'codigo' => sprintf('EST-%03d', $i),
                'cen_hie_dependency_id' => 1,
                'nombre_oficial' => 'Establecimiento '.$i,
                'nombre_corto' => sprintf('E%03d', $i),
            ]);
        }
        $this->createMinimalCenCenter([
            'codigo' => 'EST-OTHER',
            'cen_hie_dependency_id' => 2,
            'nombre_oficial' => 'Otro SS',
            'nombre_corto' => 'OTH',
        ]);

        $page1 = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListEstablecimientosTool::class, [
                    'per_page' => 100,
                    'page' => 1,
                ])
                ->assertOk()
        );
        $page2 = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListEstablecimientosTool::class, [
                    'per_page' => 100,
                    'page' => 2,
                ])
                ->assertOk()
        );

        $this->assertSame(1, (int) $page1['meta']['current_page']);
        $this->assertSame(2, (int) $page2['meta']['current_page']);
        $this->assertSame(101, (int) $page1['meta']['total']);
        $this->assertCount(100, $page1['data']);
        $this->assertCount(1, $page2['data']);

        $ids1 = collect($page1['data'])->pluck('id')->all();
        $ids2 = collect($page2['data'])->pluck('id')->all();
        $this->assertEmpty(array_intersect($ids1, $ids2));
        $this->assertCount(101, array_unique(array_merge($ids1, $ids2)));

        $adapterPage2 = app(CenCentersReadAdapter::class)->paginate(1, null, null, null, null, null, 100, 2);
        $this->assertSame(2, $adapterPage2->currentPage());
        $this->assertSame(101, $adapterPage2->total());
    }

    /** @test */
    public function api_v1_resultados_also_maps_establecimiento_from_nombre_oficial(): void
    {
        $center = $this->createMinimalCenCenter([
            'codigo' => 'API-RES',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'Centro API',
            'nombre_corto' => 'CAPI',
        ]);
        $indicator = $this->createMetIndicator($this->year2026);
        $this->createMetResult($indicator, $center, 1, $this->year2026);

        app(YearContext::class)->setCurrent($this->year2026);
        Sanctum::actingAs($this->agent, ['read']);

        $this->getJson('/api/v1/resultados?instrumento=metas&indicador_id='.$indicator->id)
            ->assertOk()
            ->assertJsonPath('data.0.establecimiento', 'Centro API')
            ->assertJsonPath('data.0.cen_hie_dependency_id', 1);
    }

    /** @test */
    public function agent_api_flag_off_still_closes_mcp(): void
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

    private function createMetIndicator(Year $year): MetIndicator
    {
        $typeId = (int) IndicatorType::query()->where('route', 'metas-18834')->value('id');

        return MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $typeId,
            'clave' => 'M-'.$year->ano.'-'.uniqid(),
            'nombre' => 'Indicador Metas '.$year->ano,
            'year_id' => $year->id,
            'orden' => 1,
            'estado' => true,
        ]));
    }

    private function createMetResult(MetIndicator $indicator, mixed $center, int $ssId, Year $year): MetResult
    {
        return MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $indicator->id,
            'cen_center_id' => $center->id,
            'cen_hie_dependency_id' => $ssId,
            'year_id' => $year->id,
            'periodo' => 'ANUAL',
            'resultado' => 0.8,
            'cumple_meta' => true,
            'etapa' => 'autoevaluacion',
        ]));
    }
}
