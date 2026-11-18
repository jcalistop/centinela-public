<?php

namespace Tests\Feature\Mcp;

use App\Enums\Metas\DataFieldComponente;
use App\Enums\Metas\DataFieldInputType;
use App\Enums\Metas\EstadoConvenioEstablecimiento;
use App\Mcp\Servers\CentinelaAgentServer;
use App\Mcp\Tools\GetCapabilitiesTool;
use App\Mcp\Tools\GetGlossaryTool;
use App\Mcp\Tools\ListFormulacionTool;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\MetDataField;
use App\Models\MetEstablishmentAgreement;
use App\Models\MetFormula;
use App\Models\MetIndicator;
use App\Models\MetIndicatorConfiguration;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Services\Agent\MetFormulacionReadAdapter;
use App\Support\YearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Laravel\Mcp\Server\Testing\TestResponse;
use ReflectionMethod;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class CentinelaAgentMcpFormulacionTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    private User $agent;

    private User $agentOtherSs;

    private Year $year2026;

    private IndicatorType $type18834;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.agent_api.enabled' => true,
            'centinela.agent_api.ip_allowlist' => [],
        ]);

        $this->year2026 = Year::unguarded(fn() => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn() => CenHieDependency::firstOrCreate(
            ['id' => 1],
            [
                'codigo' => 'SS01',
                'nombre' => 'Servicio Uno',
                'tipo_dependencia' => 'SERVICIO',
                'habilitado' => true,
            ]
        ));
        CenHieDependency::unguarded(fn() => CenHieDependency::firstOrCreate(
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
            'email' => 'mcp-formulacion@example.com',
            'name' => 'Agente Formulación',
            'role_id' => $role->id,
        ]);
        $this->agent->forceFill(['cen_hie_dependency_id' => 1, 'role_id' => $role->id])->save();
        $this->agent->role()->associate($role)->save();

        $this->agentOtherSs = User::factory()->create([
            'email' => 'mcp-formulacion-ss2@example.com',
            'role_id' => $role->id,
        ]);
        $this->agentOtherSs->forceFill(['cen_hie_dependency_id' => 2, 'role_id' => $role->id])->save();
        $this->agentOtherSs->role()->associate($role)->save();

        $this->type18834 = IndicatorType::query()->firstOrCreate(
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
    public function list_formulacion_appears_in_capabilities_and_returns_row(): void
    {
        $caps = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetCapabilitiesTool::class, [])
                ->assertOk()
        );
        $names = collect($caps['tools'])->pluck('name')->all();
        $this->assertContains('list-formulacion-tool', $names);
        $this->assertTrue(
            collect($caps['ritual'])->contains(
                fn($step) => is_string($step) && str_contains($step, 'list-formulacion-tool')
            )
        );

        $center = $this->createMinimalCenCenter([
            'codigo' => 'FORM-A',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'Centro Formulación A',
            'nombre_corto' => 'CFA',
        ]);
        $indicator = $this->createMetIndicator();
        MetFormula::unguarded(fn() => MetFormula::create([
            'met_indicator_id' => $indicator->id,
            'expression' => ['num', '/', 'den'],
            'description' => 'Numerador / Denominador',
        ]));
        MetDataField::unguarded(fn() => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Num',
            'variable_key' => 'num',
            'componente' => DataFieldComponente::Numerador,
            'input_type' => DataFieldInputType::Anual,
            'fuente' => 'REM',
            'orden' => 1,
        ]));
        MetDataField::unguarded(fn() => MetDataField::create([
            'met_indicator_id' => $indicator->id,
            'label' => 'Den',
            'variable_key' => 'den',
            'componente' => DataFieldComponente::Denominador,
            'input_type' => DataFieldInputType::Anual,
            'fuente' => 'DEIS',
            'orden' => 2,
        ]));
        $config = $this->createConfig($indicator, $center, [
            'aplica' => true,
            'seleccionado' => true,
            'ponderacion' => 15,
            'meta_efectiva' => 0.85,
        ]);
        $this->createAgreement($center, EstadoConvenioEstablecimiento::Enviado, $this->agent);

        // Legacy aplica=null → true (schema actual NOT NULL; se valida en mapRow en memoria).
        $config->setAttribute('aplica', null);
        $config->setRelation('metIndicator', $indicator->fresh(['indicatorType', 'formula', 'dataFields']));
        $config->setRelation('center', $center);
        $config->setRelation('agentAgreement', null);
        $legacy = app(MetFormulacionReadAdapter::class)->mapRow($config);
        $this->assertTrue($legacy['aplica']);

        Session::forget('indicator_year');
        $beforeSession = Session::get('indicator_year');

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 2026,
                ])
                ->assertOk()
        );

        $this->assertNull($beforeSession);
        $this->assertNull(Session::get('indicator_year'));
        $this->assertCount(1, $payload['data']);
        $row = $payload['data'][0];
        $this->assertSame($center->id, (int) $row['cen_center_id']);
        $this->assertSame('Centro Formulación A', $row['establecimiento']);
        $this->assertSame('FORM-A', $row['establecimiento_codigo']);
        $this->assertSame($indicator->id, (int) $row['indicador_id']);
        $this->assertSame('18834', $row['ley']);
        $this->assertSame('ENVIADO', $row['estado_convenio']);
        $this->assertSame('Enviado', $row['estado_convenio_label']);
        $this->assertNotEmpty($row['submitted_at']);
        $this->assertSame($this->agent->id, (int) $row['submitted_by']['id']);
        $this->assertSame('Agente Formulación', $row['submitted_by']['nombre']);
        $this->assertArrayNotHasKey('email', $row['submitted_by']);
        $this->assertTrue($row['aplica']);
        $this->assertTrue($row['comprometido']);
        $this->assertTrue($row['en_convenio']);
        $this->assertSame('15.00', $row['ponderacion']);
        $this->assertSame('0.8500', $row['meta_comprometida']);
        $this->assertSame('0.9000', $row['meta_instrumento']);
        $this->assertSame('0.8500', $row['meta_vigente']);
        $this->assertNull($row['justificacion']);
        $this->assertSame('Numerador / Denominador', $row['formula_descripcion']);
        $this->assertSame('REM', $row['fuente_numerador']);
        $this->assertSame('DEIS', $row['fuente_denominador']);
        $this->assertSame('ANUAL', $row['periodicidad']);
        $this->assertCount(2, $row['campos_datos']);
        $this->assertSame('numerador', $row['campos_datos'][0]['role']);
        $this->assertSame(1, (int) $payload['meta']['total']);
    }

    /** @test */
    public function list_formulacion_uses_default_year_without_writing_session(): void
    {
        app(YearContext::class)->setCurrent($this->year2026);
        Session::forget('indicator_year');

        $center = $this->createMinimalCenCenter([
            'codigo' => 'FORM-DEF',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'Centro Default',
            'nombre_corto' => 'CD',
        ]);
        $indicator = $this->createMetIndicator();
        $this->createConfig($indicator, $center, ['seleccionado' => false]);

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                ])
                ->assertOk()
        );

        $this->assertNull(Session::get('indicator_year'));
        $this->assertCount(1, $payload['data']);
        $this->assertFalse($payload['data'][0]['comprometido']);
        $this->assertFalse($payload['data'][0]['en_convenio']);
        $this->assertNull($payload['data'][0]['estado_convenio']);
        $this->assertSame('0.9000', $payload['data'][0]['meta_vigente']);
    }

    /** @test */
    public function list_formulacion_ley_ausente_and_sin_datos(): void
    {
        $ausente = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '19813',
                    'year' => 2026,
                ])
                ->assertOk()
        );
        $this->assertSame([], $ausente['data']);
        $this->assertSame('ausente', $ausente['meta']['empty_reason']);

        $sinDatos = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 2026,
                ])
                ->assertOk()
        );
        $this->assertSame([], $sinDatos['data']);
        $this->assertSame('sin_datos', $sinDatos['meta']['empty_reason']);
    }

    /** @test */
    public function list_formulacion_fuera_de_alcance_for_other_ss_or_missing_center(): void
    {
        $other = $this->createMinimalCenCenter([
            'codigo' => 'FORM-SS2',
            'cen_hie_dependency_id' => 2,
            'nombre_oficial' => 'Otro SS',
            'nombre_corto' => 'OSS',
        ]);
        $indicator = $this->createMetIndicator();
        $this->createConfig($indicator, $other, [
            'cen_hie_dependency_id' => 2,
            'seleccionado' => true,
        ]);

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 2026,
                    'establecimiento_id' => $other->id,
                ])
                ->assertOk()
        );
        $this->assertSame([], $payload['data']);
        $this->assertSame('fuera_de_alcance', $payload['meta']['empty_reason']);

        $missing = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 2026,
                    'establecimiento_id' => 999999,
                ])
                ->assertOk()
        );
        $this->assertSame('fuera_de_alcance', $missing['meta']['empty_reason']);
    }

    /** @test */
    public function list_formulacion_does_not_hide_aplica_false_by_default(): void
    {
        $center = $this->createMinimalCenCenter([
            'codigo' => 'FORM-APL',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'Centro Aplica',
            'nombre_corto' => 'CA',
        ]);
        $indicator = $this->createMetIndicator();
        $this->createConfig($indicator, $center, [
            'aplica' => false,
            'seleccionado' => false,
        ]);

        $all = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 2026,
                ])
                ->assertOk()
        );
        $this->assertCount(1, $all['data']);
        $this->assertFalse($all['data'][0]['aplica']);
        $this->assertNull($all['data'][0]['meta_comprometida']);
        $this->assertNull($all['data'][0]['meta_vigente']);
        $this->assertSame('0.9000', $all['data'][0]['meta_instrumento']);
        $this->assertNull($all['data'][0]['met_measurement_unit_id']);

        $filtered = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 2026,
                    'aplica' => true,
                ])
                ->assertOk()
        );
        $this->assertSame([], $filtered['data']);
        $this->assertSame('sin_datos', $filtered['meta']['empty_reason']);
    }

    /** @test */
    public function list_formulacion_unknown_year_is_json_error(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 1999,
                ])
                ->assertOk()
        );

        $this->assertSame('NOT_FOUND', $payload['error']['code']);
        $this->assertSame(1999, (int) $payload['error']['details']['year']);
        $this->assertNotEmpty($payload['error']['request_id']);
    }

    /** @test */
    public function list_formulacion_caps_per_page_at_100(): void
    {
        $indicator = $this->createMetIndicator();
        for ($i = 1; $i <= 101; $i++) {
            $center = $this->createMinimalCenCenter([
                'codigo' => sprintf('FORM-P-%03d', $i),
                'cen_hie_dependency_id' => 1,
                'nombre_oficial' => 'Centro ' . $i,
                'nombre_corto' => 'C' . $i,
            ]);
            $this->createConfig($indicator, $center, ['seleccionado' => ($i % 2 === 0)]);
        }

        $page1 = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 2026,
                    'per_page' => 500,
                    'page' => 1,
                ])
                ->assertOk()
        );

        $this->assertSame(100, (int) $page1['meta']['per_page']);
        $this->assertCount(100, $page1['data']);
        $this->assertSame(101, (int) $page1['meta']['total']);
    }

    /** @test */
    public function list_formulacion_never_exposes_validado(): void
    {
        $center = $this->createMinimalCenCenter([
            'codigo' => 'FORM-BOR',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'Centro Borrador',
            'nombre_corto' => 'CB',
        ]);
        $indicator = $this->createMetIndicator();
        $this->createConfig($indicator, $center, ['seleccionado' => true]);
        $this->createAgreement($center, EstadoConvenioEstablecimiento::Borrador, null);

        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 2026,
                ])
                ->assertOk()
        );

        $this->assertSame('BORRADOR', $payload['data'][0]['estado_convenio']);
        $this->assertNotContains('VALIDADO', array_column($payload['data'], 'estado_convenio'));
        $this->assertFalse($payload['data'][0]['en_convenio']);
    }

    /** @test */
    public function list_formulacion_validation_errors_for_ley_year_and_estado(): void
    {
        $missingLey = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'year' => 2026,
                ])
                ->assertOk()
        );
        $this->assertSame('VALIDATION_ERROR', $missingLey['error']['code']);

        $badYear = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 'abc',
                ])
                ->assertOk()
        );
        $this->assertSame('VALIDATION_ERROR', $badYear['error']['code']);
        $this->assertSame('abc', $badYear['error']['details']['year']);

        $badEstado = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 2026,
                    'estado' => 'VALIDADO',
                ])
                ->assertOk()
        );
        $this->assertSame('VALIDATION_ERROR', $badEstado['error']['code']);
        $this->assertSame('VALIDADO', $badEstado['error']['details']['estado']);
    }

    /** @test */
    public function list_formulacion_filters_by_estado_and_hides_other_ss_without_center_filter(): void
    {
        $centerSs1 = $this->createMinimalCenCenter([
            'codigo' => 'FORM-E1',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'SS1 Enviado',
            'nombre_corto' => 'E1',
        ]);
        $centerSs1B = $this->createMinimalCenCenter([
            'codigo' => 'FORM-B1',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'SS1 Borrador',
            'nombre_corto' => 'B1',
        ]);
        $centerSs1Sin = $this->createMinimalCenCenter([
            'codigo' => 'FORM-N1',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'SS1 Sin convenio',
            'nombre_corto' => 'N1',
        ]);
        $centerSs2 = $this->createMinimalCenCenter([
            'codigo' => 'FORM-E2',
            'cen_hie_dependency_id' => 2,
            'nombre_oficial' => 'SS2',
            'nombre_corto' => 'E2',
        ]);
        $indicator = $this->createMetIndicator();
        $this->createConfig($indicator, $centerSs1, ['seleccionado' => true]);
        $this->createConfig($indicator, $centerSs1B, ['seleccionado' => true]);
        $this->createConfig($indicator, $centerSs1Sin, ['seleccionado' => true]);
        $this->createConfig($indicator, $centerSs2, [
            'cen_hie_dependency_id' => 2,
            'seleccionado' => true,
        ]);
        $this->createAgreement($centerSs1, EstadoConvenioEstablecimiento::Enviado, $this->agent);
        $this->createAgreement($centerSs1B, EstadoConvenioEstablecimiento::Borrador, null);

        $enviados = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 2026,
                    'estado' => 'ENVIADO',
                ])
                ->assertOk()
        );
        $this->assertCount(1, $enviados['data']);
        $this->assertSame($centerSs1->id, (int) $enviados['data'][0]['cen_center_id']);
        $this->assertSame('ENVIADO', $enviados['data'][0]['estado_convenio']);

        $borradores = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 2026,
                    'estado' => 'BORRADOR',
                ])
                ->assertOk()
        );
        $ids = collect($borradores['data'])->pluck('cen_center_id')->map(fn($id) => (int) $id)->all();
        $this->assertContains($centerSs1B->id, $ids);
        $this->assertNotContains($centerSs1->id, $ids);
        $this->assertNotContains($centerSs1Sin->id, $ids, 'sin fila de convenio no entra en filtro BORRADOR');
        $this->assertNotContains($centerSs2->id, $ids);
        $this->assertSame('BORRADOR', $borradores['data'][0]['estado_convenio']);

        $allSs1 = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(ListFormulacionTool::class, [
                    'ley' => '18834',
                    'year' => 2026,
                ])
                ->assertOk()
        );
        $allIds = collect($allSs1['data'])->pluck('cen_center_id')->map(fn($id) => (int) $id)->all();
        $this->assertContains($centerSs1->id, $allIds);
        $this->assertContains($centerSs1B->id, $allIds);
        $this->assertContains($centerSs1Sin->id, $allIds);
        $this->assertNotContains($centerSs2->id, $allIds);
        $sin = collect($allSs1['data'])->firstWhere('cen_center_id', $centerSs1Sin->id);
        $this->assertNull($sin['estado_convenio']);
    }

    /** @test */
    public function get_glossary_explains_formulacion_terms(): void
    {
        $payload = $this->toolJson(
            CentinelaAgentServer::actingAs($this->agent)
                ->tool(GetGlossaryTool::class, [])
                ->assertOk()
        );

        $terms = collect($payload['entries'] ?? $payload['data'] ?? [])
            ->pluck('term')
            ->filter()
            ->values()
            ->all();

        if ($terms === [] && isset($payload['version'])) {
            $raw = json_encode($payload);
            $this->assertStringContainsString('estado_convenio', (string) $raw);
            $this->assertStringContainsString('fuera_de_alcance', (string) $raw);
            $this->assertStringContainsString('formulacion', (string) $raw);
            $this->assertStringContainsString('justificacion', (string) $raw);

            return;
        }

        $this->assertContains('estado_convenio', $terms);
        $this->assertContains('fuera_de_alcance', $terms);
        $this->assertContains('formulacion', $terms);
        $this->assertContains('justificacion', $terms);
        $this->assertContains('meta_vigente', $terms);
        $this->assertContains('unidad_medida_formulacion', $terms);
        $this->assertContains('en_convenio', $terms);
    }

    /** @test */
    public function list_formulacion_requires_agent_with_ss(): void
    {
        $role = Role::firstOrCreate(['name' => 'agente'], ['display_name' => 'Agente']);
        $orphan = User::factory()->create([
            'email' => 'mcp-formulacion-orphan@example.com',
            'role_id' => $role->id,
        ]);
        $orphan->forceFill(['cen_hie_dependency_id' => 0, 'role_id' => $role->id])->save();
        $orphan->role()->associate($role)->save();

        CentinelaAgentServer::actingAs($orphan)
            ->tool(ListFormulacionTool::class, [
                'ley' => '18834',
                'year' => 2026,
            ])
            ->assertHasErrors();
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

    private function createMetIndicator(): MetIndicator
    {
        return MetIndicator::unguarded(fn() => MetIndicator::create([
            'indicator_type_id' => $this->type18834->id,
            'clave' => 'M-FORM-' . uniqid(),
            'nombre' => 'Indicador Formulación',
            'year_id' => $this->year2026->id,
            'meta_valor' => 0.9,
            'orden' => 1,
            'estado' => true,
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createConfig(MetIndicator $indicator, mixed $center, array $overrides = []): MetIndicatorConfiguration
    {
        return MetIndicatorConfiguration::unguarded(fn() => MetIndicatorConfiguration::create(array_merge([
            'met_indicator_id' => $indicator->id,
            'year_id' => $this->year2026->id,
            'cen_hie_dependency_id' => 1,
            'cen_center_id' => $center->id,
            'aplica' => true,
            'agrupa_ss' => false,
            'seleccionado' => false,
            'ponderacion' => null,
            'meta_efectiva' => null,
        ], $overrides)));
    }

    private function createAgreement(mixed $center, EstadoConvenioEstablecimiento $estado, ?User $submittedBy): MetEstablishmentAgreement
    {
        return MetEstablishmentAgreement::unguarded(fn() => MetEstablishmentAgreement::create([
            'cen_center_id' => $center->id,
            'year_id' => $this->year2026->id,
            'indicator_type_id' => $this->type18834->id,
            'cen_hie_dependency_id' => 1,
            'estado' => $estado,
            'submitted_at' => $estado === EstadoConvenioEstablecimiento::Enviado ? now() : null,
            'submitted_by' => $submittedBy?->id,
        ]));
    }
}
