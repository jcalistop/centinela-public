<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AgentApiV1ContractTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.agent_api.enabled' => true,
            'centinela.agent_api.ip_allowlist' => [],
        ]);

        Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $role = Role::firstOrCreate(['name' => 'agente'], ['display_name' => 'Agente']);
        $user = User::factory()->create([
            'email' => 'agente-v1@example.com',
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $user->role()->associate($role);
        $user->save();

        $this->token = $user->createToken('agent', ['read'], now()->addHour())->plainTextToken;
    }

    /** @test */
    public function openapi_and_glossary_are_readable(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.title', 'Centinela Agent API v1');

        $this->withToken($this->token)
            ->getJson('/api/v1/glossary')
            ->assertOk()
            ->assertJsonStructure(['version', 'entries']);
    }

    /** @test */
    public function health_exposes_version_default_year_and_flag(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('agent_api_enabled', true)
            ->assertJsonStructure(['version', 'default_year', 'year']);
    }

    /** @test */
    public function context_and_instrumentos_are_neutral(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/context')
            ->assertOk()
            ->assertJsonPath('cen_hie_dependency_id', 1)
            ->assertJsonPath('year', 2026);

        $this->withToken($this->token)
            ->getJson('/api/v1/instrumentos')
            ->assertOk()
            ->assertJsonFragment(['code' => 'comges'])
            ->assertJsonFragment(['code' => 'metas']);
    }

    /** @test */
    public function post_on_v1_is_method_not_allowed(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/indicadores', ['instrumento' => 'comges'])
            ->assertStatus(405)
            ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
    }

    /** @test */
    public function domain_get_routes_on_api_v1_have_no_write_handlers(): void
    {
        $domainGets = [
            'api/v1/health',
            'api/v1/years',
            'api/v1/context',
            'api/v1/instrumentos',
            'api/v1/compromisos',
            'api/v1/indicadores',
            'api/v1/resultados',
            'api/v1/dashboard/alertas',
            'api/v1/glossary',
            'api/v1/openapi.json',
        ];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array($uri, $domainGets, true) && ! str_starts_with($uri, 'api/v1/indicadores/{')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $this->assertSame(
                    'GET',
                    $method,
                    "Handler de dominio no debe mutar: {$method} {$uri}"
                );
            }
        }
    }

    /** @test */
    public function flag_off_still_denies_surface(): void
    {
        config(['centinela.agent_api.enabled' => false]);

        $this->withToken($this->token)
            ->getJson('/api/v1/years')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AGENT_API_DISABLED');
    }
}
