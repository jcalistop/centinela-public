<?php

namespace Tests\Feature\Api\V1;

use App\Models\CenHieDependency;
use App\Models\ComCommitment;
use App\Models\ComIndicator;
use App\Models\IndicatorType;
use App\Models\MetIndicator;
use App\Models\MetResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Support\YearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMinimalCenCenter;
use Tests\TestCase;

class AgentApiV1ReadSmokeTest extends TestCase
{
    use CreatesMinimalCenCenter;
    use RefreshDatabase;

    private User $agentA;

    private User $agentB;

    private Year $year;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.agent_api.enabled' => true,
            'centinela.agent_api.ip_allowlist' => [],
        ]);

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        app(YearContext::class)->setCurrent($this->year);

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio Uno',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 2,
            'codigo' => 'SS02',
            'nombre' => 'Servicio Dos',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $centerA = $this->createMinimalCenCenter([
            'codigo' => 'CEN-A',
            'cen_hie_dependency_id' => 1,
            'nombre_oficial' => 'Centro A',
            'nombre_corto' => 'A',
        ]);
        $centerB = $this->createMinimalCenCenter([
            'codigo' => 'CEN-B',
            'cen_hie_dependency_id' => 2,
            'nombre_oficial' => 'Centro B',
            'nombre_corto' => 'B',
        ]);

        $role = Role::firstOrCreate(['name' => 'agente'], ['display_name' => 'Agente']);

        $this->agentA = User::factory()->create([
            'email' => 'agente-a@example.com',
            'role_id' => $role->id,
        ]);
        $this->agentA->forceFill(['cen_hie_dependency_id' => 1, 'role_id' => $role->id])->save();
        $this->agentA->role()->associate($role);
        $this->agentA->save();

        $this->agentB = User::factory()->create([
            'email' => 'agente-b@example.com',
            'role_id' => $role->id,
        ]);
        $this->agentB->forceFill(['cen_hie_dependency_id' => 2, 'role_id' => $role->id])->save();
        $this->agentB->role()->associate($role);
        $this->agentB->save();

        $this->agentA->createToken('a', ['read'], now()->addHour());
        $this->agentB->createToken('b', ['read'], now()->addHour());

        $commitment = ComCommitment::unguarded(fn () => ComCommitment::create([
            'clave' => 'C1',
            'nombre' => 'Compromiso prueba',
            'orden' => 1,
            'indicator_type_id' => 1,
            'year_id' => $this->year->id,
        ]));

        ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $commitment->id,
            'clave' => 'I1',
            'nombre' => 'Indicador COMGES',
            'orden' => 1,
            'ponderacion_indicador' => 100,
        ]));

        $type = IndicatorType::query()->firstOrCreate(
            ['route' => 'metas-18834'],
            [
                'clave_indicador' => 'M18834',
                'nombre_indicador' => 'Metas 18.834',
                'estado' => true,
                'orden' => 1,
            ]
        );

        $met = MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $type->id,
            'clave' => 'M1',
            'nombre' => 'Indicador Metas',
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $met->id,
            'cen_center_id' => $centerA->id,
            'cen_hie_dependency_id' => 1,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'resultado' => 0.8,
            'cumple_meta' => true,
            'etapa' => 'autoevaluacion',
        ]));

        MetResult::unguarded(fn () => MetResult::create([
            'met_indicator_id' => $met->id,
            'cen_center_id' => $centerB->id,
            'cen_hie_dependency_id' => 2,
            'year_id' => $this->year->id,
            'periodo' => 'ANUAL',
            'resultado' => 0.5,
            'cumple_meta' => false,
            'etapa' => 'autoevaluacion',
        ]));
    }

    /** @test */
    public function lists_comges_and_metas_indicators_with_instrumento_attribute(): void
    {
        Sanctum::actingAs($this->agentA, ['read']);

        $comges = $this->getJson('/api/v1/indicadores?instrumento=comges')
            ->assertOk()
            ->json();

        $this->assertNotEmpty($comges['data']);
        $this->assertSame('comges', $comges['data'][0]['instrumento']);

        $metas = $this->getJson('/api/v1/indicadores?instrumento=metas&ley=18834')
            ->assertOk()
            ->json();

        $this->assertNotEmpty($metas['data']);
        $this->assertSame('metas', $metas['data'][0]['instrumento']);
    }

    /** @test */
    public function resultados_are_scoped_to_token_ss(): void
    {
        $this->assertSame(2, (int) $this->agentB->fresh()->cen_hie_dependency_id);
        $this->assertSame(1, MetResult::query()->where('cen_hie_dependency_id', 2)->count());

        $metId = MetIndicator::query()->value('id');

        Sanctum::actingAs($this->agentA, ['read']);
        $forA = $this->getJson('/api/v1/resultados?instrumento=metas&indicador_id='.$metId)
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $forA);
        $this->assertSame(1, (int) $forA[0]['cen_hie_dependency_id']);

        Sanctum::actingAs($this->agentB, ['read']);
        $contextB = $this->getJson('/api/v1/context')
            ->assertOk()
            ->json();
        $this->assertSame($this->agentB->id, (int) $contextB['user_id']);
        $this->assertSame(2, (int) $contextB['cen_hie_dependency_id']);

        $forB = $this->getJson('/api/v1/resultados?instrumento=metas&indicador_id='.$metId)
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $forB);
        $this->assertSame(2, (int) $forB[0]['cen_hie_dependency_id']);
    }

    /** @test */
    public function dashboard_alertas_endpoint_responds(): void
    {
        Sanctum::actingAs($this->agentA, ['read']);

        $this->getJson('/api/v1/dashboard/alertas')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['year', 'cen_hie_dependency_id', 'count']]);
    }

    /** @test */
    public function compromisos_list_is_paginated(): void
    {
        Sanctum::actingAs($this->agentA, ['read']);

        $this->getJson('/api/v1/compromisos')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total']]);
    }
}
