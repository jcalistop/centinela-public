<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CentinelaAgentServer;
use App\Mcp\Tools\GetIndicadorTool;
use App\Models\CenHieDependency;
use App\Models\ComAction;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\ComIndicatorCutoff;
use App\Models\ComIndicatorSetting;
use App\Models\ComSensitivityDependency;
use App\Models\ComSensitivityTable;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Support\YearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Testing\TestResponse;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\TestCase;

class CentinelaAgentMcpComgesDetalleTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $agentOtherSs;

    private User $referenteSs1;

    private User $referenteSs2;

    private Year $year;

    private ComIndicator $indicator;

    private ComAction $actionWithData;

    private ComAction $actionWithoutData;

    private ComSensitivityTable $tableSelected;

    private ComSensitivityTable $tableNotSelected;

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

        $this->agent = $this->createAgent('mcp-comges-ss1@example.com', 'Agente SS1', $role, 1);
        $this->agentOtherSs = $this->createAgent('mcp-comges-ss2@example.com', 'Agente SS2', $role, 2);
        $this->referenteSs1 = $this->createAgent('referente-ss1@example.com', 'Referente SS1', $role, 1);
        $this->referenteSs2 = $this->createAgent('referente-ss2@example.com', 'Referente SS2', $role, 2);

        $this->buildComgesFixture();
    }

    /** @test */
    public function detail_includes_formula_umbrales_and_referentes(): void
    {
        $payload = $this->toolJson($this->mcpDetail($this->agent));

        $this->assertSame($this->indicator->id, (int) $payload['data']['id']);
        $this->assertSame('score_0_4', $payload['data']['escala']);

        $acciones = collect($payload['data']['cutoffs'][0]['acciones']);
        $withData = $acciones->firstWhere('id', $this->actionWithData->id);

        $this->assertSame('Meta textual de la accion', $withData['meta_texto']);
        $this->assertSame('(A / B) * 100', $withData['formula']);
        $this->assertCount(2, $withData['umbrales']);

        $selected = collect($withData['umbrales'])->firstWhere('id', $this->tableSelected->id);
        $notSelected = collect($withData['umbrales'])->firstWhere('id', $this->tableNotSelected->id);

        $this->assertTrue($selected['seleccionada_ss']);
        $this->assertFalse($notSelected['seleccionada_ss']);
        $this->assertEqualsWithDelta(90.0, (float) $selected['meta_numero'], 0.001);
        $this->assertSame('>=', $selected['valores'][0]['operador_minimo']);
        $this->assertEqualsWithDelta(90.0, (float) $selected['valores'][0]['valor_minimo'], 0.001);
        $this->assertEqualsWithDelta(4.0, (float) $selected['valores'][0]['cumplimiento'], 0.001);
    }

    /** @test */
    public function referentes_are_scoped_to_the_token_ss_and_hide_email(): void
    {
        $payload = $this->toolJson($this->mcpDetail($this->agent));
        $referentes = $payload['data']['referentes'];

        $this->assertCount(1, $referentes);
        $this->assertSame($this->referenteSs1->id, (int) $referentes[0]['id']);
        $this->assertSame('Referente SS1', $referentes[0]['name']);
        $this->assertArrayNotHasKey('email', $referentes[0]);
        $this->assertSame(['id', 'name'], array_keys($referentes[0]));

        $otherPayload = $this->toolJson($this->mcpDetail($this->agentOtherSs));
        $otherReferentes = $otherPayload['data']['referentes'];

        $this->assertCount(1, $otherReferentes);
        $this->assertSame($this->referenteSs2->id, (int) $otherReferentes[0]['id']);
        $this->assertNotSame($this->referenteSs1->id, (int) $otherReferentes[0]['id']);
    }

    /** @test */
    public function seleccionada_ss_follows_the_token_ss(): void
    {
        $payload = $this->toolJson($this->mcpDetail($this->agentOtherSs));
        $umbrales = collect($payload['data']['cutoffs'][0]['acciones'])
            ->firstWhere('id', $this->actionWithData->id)['umbrales'];

        $selected = collect($umbrales)->firstWhere('id', $this->tableSelected->id);
        $notSelected = collect($umbrales)->firstWhere('id', $this->tableNotSelected->id);

        $this->assertFalse($selected['seleccionada_ss']);
        $this->assertTrue($notSelected['seleccionada_ss']);
    }

    /** @test */
    public function detail_keeps_keys_present_when_data_is_empty(): void
    {
        $payload = $this->toolJson($this->mcpDetail($this->agent));
        $acciones = collect($payload['data']['cutoffs'][0]['acciones']);
        $empty = $acciones->firstWhere('id', $this->actionWithoutData->id);

        $this->assertArrayHasKey('meta_texto', $empty);
        $this->assertNull($empty['meta_texto']);
        $this->assertArrayHasKey('formula', $empty);
        $this->assertNull($empty['formula']);
        $this->assertSame([], $empty['umbrales']);

        $indicatorWithoutSettings = $this->toolJson($this->mcpDetail($this->agent, $this->indicatorWithoutReferentes()));
        $this->assertSame([], $indicatorWithoutSettings['data']['referentes']);
    }

    /** @test */
    public function api_v1_detail_serializes_the_same_shape(): void
    {
        Sanctum::actingAs($this->agent, ['read']);

        $response = $this->getJson('/api/v1/indicadores/'.$this->indicator->id.'?instrumento=comges')
            ->assertOk()
            ->assertJsonPath('data.escala', 'score_0_4')
            ->assertJsonStructure([
                'data' => [
                    'id', 'instrumento', 'clave', 'nombre', 'escala', 'referentes', 'cutoffs',
                ],
            ]);

        $withData = collect($response->json('data.cutoffs.0.acciones'))->firstWhere('id', $this->actionWithData->id);

        $this->assertSame('(A / B) * 100', $withData['formula']);
        $this->assertCount(2, $withData['umbrales']);
        $this->assertCount(1, $response->json('data.referentes'));
    }

    /** @test */
    public function metas_detail_does_not_inherit_comges_keys(): void
    {
        $typeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'M18834-DET',
            'nombre_indicador' => 'Metas 18.834',
            'route' => 'metas-18834',
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $metId = (int) DB::table('met_indicators')->insertGetId([
            'indicator_type_id' => $typeId,
            'clave' => 'M-DET-1',
            'nombre' => 'Indicador Metas detalle',
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetIndicadorTool::class, [
                    'instrumento' => 'metas',
                    'id' => $metId,
                    'year' => 2026,
                ])
                ->assertOk()
        );

        $this->assertSame($metId, (int) $payload['data']['id']);
        $this->assertArrayNotHasKey('cutoffs', $payload['data']);
        $this->assertArrayNotHasKey('formula', $payload['data']);
        $this->assertArrayNotHasKey('umbrales', $payload['data']);
        $this->assertArrayNotHasKey('referentes', $payload['data']);
    }

    /** @test */
    public function detail_is_readonly_and_does_not_mutate_the_catalog(): void
    {
        $before = DB::table('com_indicators')->where('id', $this->indicator->id)->first();

        $this->mcpDetail($this->agent)->assertOk();

        $after = DB::table('com_indicators')->where('id', $this->indicator->id)->first();
        $this->assertEquals($before, $after);

        $ref = new \ReflectionClass(GetIndicadorTool::class);
        $this->assertNotEmpty($ref->getAttributes(IsReadOnly::class));
    }

    private function mcpDetail(User $agent, ?ComIndicator $indicator = null): TestResponse
    {
        return CentinelaAgentServer::actingAs($agent)
            ->tool(GetIndicadorTool::class, [
                'instrumento' => 'comges',
                'id' => ($indicator ?? $this->indicator)->id,
                'year' => 2026,
            ]);
    }

    private function createAgent(string $email, string $name, Role $role, int $ssId): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role_id' => $role->id,
        ]);
        $user->forceFill(['cen_hie_dependency_id' => $ssId, 'role_id' => $role->id])->save();
        $user->role()->associate($role)->save();

        return $user;
    }

    private function buildComgesFixture(): void
    {
        $typeId = (int) DB::table('indicator_types')->insertGetId([
            'clave_indicador' => 'COMGES-DET',
            'nombre_indicador' => 'COMGES detalle',
            'route' => 'compromisos-gestion',
            'estado' => 1,
            'orden' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $commitment = ComCommitment::unguarded(fn () => ComCommitment::create([
            'indicator_type_id' => $typeId,
            'clave' => 'C-DET',
            'nombre' => 'Compromiso detalle',
            'orden' => 1,
            'year_id' => $this->year->id,
        ]));

        $this->indicator = ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $commitment->id,
            'clave' => 'I-DET',
            'nombre' => 'Indicador COMGES detalle',
            'ponderacion_indicador' => 100,
            'orden' => 1,
        ]));

        $cutoffId = (int) DB::table('cutoffs')->insertGetId([
            'clave' => 'CORTE I',
            'tipo_corte' => 'TRIMESTRAL',
            'meses' => json_encode([1, 2, 3]),
            'nombre_corte' => 'Corte I',
            'orden' => 1,
            'habilitado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indicatorCutoff = ComIndicatorCutoff::unguarded(fn () => ComIndicatorCutoff::create([
            'com_indicator_id' => $this->indicator->id,
            'cutoff_id' => $cutoffId,
            'ponderacion_corte' => 100,
        ]));

        $this->actionWithData = ComAction::unguarded(fn () => ComAction::create([
            'com_indicator_cutoff_id' => $indicatorCutoff->id,
            'numero' => '1',
            'meta_texto' => 'Meta textual de la accion',
            'formula' => '(A / B) * 100',
            'tipo_medio' => null,
        ]));

        $this->actionWithoutData = ComAction::unguarded(fn () => ComAction::create([
            'com_indicator_cutoff_id' => $indicatorCutoff->id,
            'numero' => '2',
            'meta_texto' => null,
            'formula' => null,
            'tipo_medio' => null,
        ]));

        $this->tableSelected = ComSensitivityTable::unguarded(fn () => ComSensitivityTable::create([
            'com_action_id' => $this->actionWithData->id,
            'meta_numero' => 90,
            'valores' => json_encode([[
                'valor_minimo' => 90,
                'operador_minimo' => '>=',
                'valor_maximo' => null,
                'operador_maximo' => '<',
                'cumplimiento' => 4,
            ]]),
        ]));

        $this->tableNotSelected = ComSensitivityTable::unguarded(fn () => ComSensitivityTable::create([
            'com_action_id' => $this->actionWithData->id,
            'meta_numero' => 75,
            'valores' => json_encode([[
                'valor_minimo' => 75,
                'operador_minimo' => '>=',
                'valor_maximo' => 90,
                'operador_maximo' => '<',
                'cumplimiento' => 3,
            ]]),
        ]));

        foreach ([[1, $this->tableSelected], [2, $this->tableNotSelected]] as [$ssId, $table]) {
            ComSensitivityDependency::unguarded(fn () => ComSensitivityDependency::create([
                'com_sensitivity_table_id' => $table->id,
                'com_action_id' => $this->actionWithData->id,
                'cen_hie_dependency_id' => $ssId,
            ]));
        }

        $this->attachReferente($this->indicator, 1, $this->referenteSs1);
        $this->attachReferente($this->indicator, 2, $this->referenteSs2);
    }

    private function attachReferente(ComIndicator $indicator, int $ssId, User $user): void
    {
        $setting = ComIndicatorSetting::unguarded(fn () => ComIndicatorSetting::create([
            'cen_hie_dependency_id' => $ssId,
            'com_indicator_id' => $indicator->id,
        ]));

        $setting->users()->attach($user->id);
    }

    private function indicatorWithoutReferentes(): ComIndicator
    {
        $commitment = ComCommitment::query()->where('year_id', $this->year->id)->firstOrFail();

        return ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $commitment->id,
            'clave' => 'I-DET-NOREF',
            'nombre' => 'Indicador COMGES sin referentes',
            'ponderacion_indicador' => 10,
            'orden' => 2,
        ]));
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
