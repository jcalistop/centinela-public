<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CentinelaAgentServer;
use App\Mcp\Tools\GetHealthTool;
use App\Mcp\Tools\ListIndicadoresTool;
use App\Models\CenHieDependency;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Support\YearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\TestCase;

class CentinelaAgentMcpCoberturaTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private Year $year2026;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.agent_api.enabled' => true,
            'centinela.agent_api.ip_allowlist' => [],
        ]);

        $this->year2026 = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        app(YearContext::class)->setCurrent($this->year2026);

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
            'email' => 'mcp-cobertura@example.com',
            'role_id' => $role->id,
        ]);
        $this->agent->forceFill(['cen_hie_dependency_id' => 1, 'role_id' => $role->id])->save();
        $this->agent->role()->associate($role)->save();
    }

    /** @test */
    public function health_reports_full_coverage_matrix_even_with_zero_rows(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetHealthTool::class, ['year' => 2026])
                ->assertOk()
        );

        $cobertura = collect($payload['cobertura']);
        $this->assertCount(4, $cobertura);

        $comges = $cobertura->firstWhere('instrumento', 'comges');
        $this->assertNotNull($comges);
        $this->assertNull($comges['ley']);
        $this->assertSame(0, $comges['indicadores_total']);

        foreach (['18834', '19664', '20707'] as $ley) {
            $row = $cobertura->first(fn (array $item): bool => $item['instrumento'] === 'metas' && $item['ley'] === $ley);
            $this->assertNotNull($row, 'Falta la ley '.$ley.' en cobertura.');
            $this->assertSame(0, $row['indicadores_total']);
        }
    }

    /** @test */
    public function health_coverage_counts_catalog_rows_per_ley_and_comges(): void
    {
        $type18834 = $this->createIndicatorType('metas-18834');
        $type20707 = $this->createIndicatorType('metas-20707');

        $this->createMetIndicator($type18834, 'M-18834-1');
        $this->createMetIndicator($type20707, 'M-20707-1');
        $this->createMetIndicator($type20707, 'M-20707-2');

        $commitment = ComCommitment::unguarded(fn () => ComCommitment::create([
            'clave' => 'C1',
            'nombre' => 'Compromiso',
            'orden' => 1,
            'indicator_type_id' => 1,
            'year_id' => $this->year2026->id,
        ]));
        ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $commitment->id,
            'clave' => 'CI1',
            'nombre' => 'Indicador COMGES',
            'orden' => 1,
            'ponderacion_indicador' => 100,
        ]));

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetHealthTool::class, ['year' => 2026])
                ->assertOk()
        );

        $cobertura = collect($payload['cobertura']);

        $this->assertSame(1, (int) $cobertura->firstWhere('instrumento', 'comges')['indicadores_total']);
        $this->assertSame(1, (int) $this->coberturaRow($cobertura, '18834')['indicadores_total']);
        $this->assertSame(0, (int) $this->coberturaRow($cobertura, '19664')['indicadores_total']);
        $this->assertSame(2, (int) $this->coberturaRow($cobertura, '20707')['indicadores_total']);
    }

    /** @test */
    public function list_indicadores_20707_without_rows_reports_sin_datos(): void
    {
        $this->createIndicatorType('metas-20707');

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListIndicadoresTool::class, [
                    'instrumento' => 'metas',
                    'ley' => '20707',
                    'year' => 2026,
                ])
                ->assertOk()
        );

        $this->assertSame([], $payload['data']);
        $this->assertSame(0, (int) $payload['meta']['total']);
        $this->assertSame('sin_datos', $payload['meta']['empty_reason']);
    }

    /** @test */
    public function list_indicadores_20707_with_rows_omits_empty_reason(): void
    {
        $type20707 = $this->createIndicatorType('metas-20707');
        $this->createMetIndicator($type20707, 'M-20707-1');
        $this->createMetIndicator($type20707, 'M-20707-2');

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListIndicadoresTool::class, [
                    'instrumento' => 'metas',
                    'ley' => '20707',
                    'year' => 2026,
                ])
                ->assertOk()
        );

        $this->assertCount(2, $payload['data']);
        $this->assertSame(2, (int) $payload['meta']['total']);
        $this->assertArrayNotHasKey('empty_reason', $payload['meta']);
    }

    /** @test */
    public function list_indicadores_unknown_ley_does_not_list_the_metas_universe(): void
    {
        $type18834 = $this->createIndicatorType('metas-18834');
        $this->createMetIndicator($type18834, 'M-18834-1');

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListIndicadoresTool::class, [
                    'instrumento' => 'metas',
                    'ley' => '19813',
                    'year' => 2026,
                ])
                ->assertOk()
        );

        // SDD-299: código fuera de catálogo = ausente (no error de validación, no universo Metas).
        $this->assertSame([], $payload['data']);
        $this->assertSame('ausente', $payload['meta']['empty_reason']);
        $this->assertSame('19813', $payload['meta']['ley']);
        $this->assertArrayNotHasKey('error', $payload);
    }

    /** @test */
    public function list_indicadores_comges_without_rows_reports_sin_datos(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListIndicadoresTool::class, [
                    'instrumento' => 'comges',
                    'year' => 2026,
                ])
                ->assertOk()
        );

        $this->assertSame([], $payload['data']);
        $this->assertSame('sin_datos', $payload['meta']['empty_reason']);
    }

    /** @test */
    public function api_v1_health_and_indicadores_share_the_coverage_contract(): void
    {
        $type20707 = $this->createIndicatorType('metas-20707');
        Sanctum::actingAs($this->agent, ['read']);

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonCount(4, 'cobertura')
            ->assertJsonPath('cobertura.0.instrumento', 'comges')
            ->assertJsonPath('cobertura.0.ley', null);

        $this->getJson('/api/v1/indicadores?instrumento=metas&ley=20707')
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.empty_reason', 'sin_datos');

        $this->createMetIndicator($type20707, 'M-API-1');

        $this->getJson('/api/v1/indicadores?instrumento=metas&ley=20707')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('meta.empty_reason');

        $this->getJson('/api/v1/indicadores?instrumento=metas&ley=19813')
            ->assertOk()
            ->assertJsonPath('meta.empty_reason', 'ausente')
            ->assertJsonPath('meta.ley', '19813')
            ->assertJsonPath('data', []);
    }

    /** @test */
    public function health_coverage_is_catalog_wide_not_scoped_to_the_token_ss(): void
    {
        $type20707 = $this->createIndicatorType('metas-20707');
        $this->createMetIndicator($type20707, 'M-GLOBAL-1');

        CenHieDependency::unguarded(fn () => CenHieDependency::firstOrCreate(
            ['id' => 2],
            [
                'codigo' => 'SS02',
                'nombre' => 'Servicio Dos',
                'tipo_dependencia' => 'SERVICIO',
                'habilitado' => true,
            ]
        ));

        $role = Role::query()->where('name', 'agente')->firstOrFail();
        $otherAgent = User::factory()->create([
            'email' => 'mcp-cobertura-ss2@example.com',
            'role_id' => $role->id,
        ]);
        $otherAgent->forceFill(['cen_hie_dependency_id' => 2, 'role_id' => $role->id])->save();

        foreach ([$this->agent, $otherAgent] as $agent) {
            $payload = $this->toolJson(
                CentinelaAgentServer::actingAs($agent)
                    ->tool(GetHealthTool::class, ['year' => 2026])
                    ->assertOk()
            );

            $this->assertSame(
                1,
                (int) $this->coberturaRow(collect($payload['cobertura']), '20707')['indicadores_total']
            );
        }
    }

    /** @test */
    public function health_unknown_year_keeps_the_existing_year_error_without_coverage_rows(): void
    {
        $response = CentinelaAgentServer::actingAs($this->agent)
            ->tool(GetHealthTool::class, ['year' => 1999]);

        $response->assertHasErrors();
        $response->assertDontSee('cobertura');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $cobertura
     * @return array<string, mixed>
     */
    private function coberturaRow($cobertura, string $ley): array
    {
        $row = $cobertura->first(fn (array $item): bool => $item['instrumento'] === 'metas' && $item['ley'] === $ley);
        $this->assertNotNull($row, 'Falta la ley '.$ley.' en cobertura.');

        return $row;
    }

    private function createIndicatorType(string $route): IndicatorType
    {
        return IndicatorType::query()->firstOrCreate(
            ['route' => $route],
            [
                'clave_indicador' => strtoupper(str_replace('-', '', $route)),
                'nombre_indicador' => $route,
                'estado' => true,
                'orden' => 1,
            ]
        );
    }

    private function createMetIndicator(IndicatorType $type, string $clave): MetIndicator
    {
        return MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $type->id,
            'clave' => $clave,
            'nombre' => 'Indicador '.$clave,
            'year_id' => $this->year2026->id,
            'orden' => 1,
            'estado' => true,
        ]));
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
