<?php

namespace Tests\Feature\Api\V1;

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
use Tests\TestCase;

class AgentApiV1SearchTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

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

        $role = Role::firstOrCreate(['name' => 'agente'], ['display_name' => 'Agente']);
        $user = User::factory()->create([
            'email' => 'agente-search@example.com',
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role)->save();
        $this->token = $user->createToken('agent', ['read'], now()->addHour())->plainTextToken;

        $commitment = ComCommitment::unguarded(fn () => ComCommitment::create([
            'clave' => 'C1',
            'nombre' => 'Compromiso prueba',
            'orden' => 1,
            'indicator_type_id' => 1,
            'year_id' => $this->year->id,
        ]));

        ComIndicator::unguarded(fn () => ComIndicator::create([
            'com_commitment_id' => $commitment->id,
            'clave' => 'I-SEARCH',
            'nombre' => 'Indicador lista de espera COMGES',
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

        MetIndicator::unguarded(fn () => MetIndicator::create([
            'indicator_type_id' => $type->id,
            'clave' => 'M-SEARCH',
            'nombre' => 'Indicador lista de espera Metas',
            'year_id' => $this->year->id,
            'orden' => 1,
            'estado' => true,
        ]));
    }

    /** @test */
    public function search_merges_comges_and_metas_without_instrumento(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/search?q=lista+de+espera')
            ->assertOk()
            ->assertJsonPath('meta.q', 'lista de espera')
            ->assertJsonPath('meta.year', 2026);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $instrumentos = collect($data)->pluck('instrumento')->unique()->sort()->values()->all();
        $this->assertSame(['comges', 'metas'], $instrumentos);
    }

    /** @test */
    public function search_rejects_short_query(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/search?q=a')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /** @test */
    public function search_requires_auth_and_flag(): void
    {
        $this->getJson('/api/v1/search?q=lista')
            ->assertStatus(401);

        config(['centinela.agent_api.enabled' => false]);

        $this->withToken($this->token)
            ->getJson('/api/v1/search?q=lista')
            ->assertStatus(403);
    }
}
