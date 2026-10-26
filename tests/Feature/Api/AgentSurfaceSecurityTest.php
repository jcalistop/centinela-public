<?php

namespace Tests\Feature\Api;

use App\Models\AgentRequestLog;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentSurfaceSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private string $agentToken;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.agent_api.enabled' => true,
            'centinela.agent_api.ip_allowlist' => [],
            'centinela.agent_api.token_ttl_minutes' => 60,
        ]);

        $role = Role::firstOrCreate(
            ['name' => 'agente'],
            ['display_name' => 'Agente'],
        );

        $this->agent = User::factory()->create([
            'email' => 'agente@example.com',
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->agent->setRelation('role', $role);
        $this->agent->role()->associate($role);
        $this->agent->save();

        $this->agentToken = $this->agent->createToken('agent', ['read'], now()->addHour())->plainTextToken;

        Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 0]));
    }

    /** @test */
    public function migrate_garantiza_rol_agente(): void
    {
        $this->assertDatabaseHas('roles', [
            'name' => 'agente',
            'display_name' => 'Agente',
        ]);
    }

    /** @test */
    public function flag_off_denies_agent_surface_with_envelope(): void
    {
        config(['centinela.agent_api.enabled' => false]);

        $this->withToken($this->agentToken)
            ->getJson('/api/v1/health')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AGENT_API_DISABLED');
    }

    /** @test */
    public function unauthenticated_request_is_denied_with_envelope(): void
    {
        $this->getJson('/api/v1/health')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /** @test */
    public function happy_path_health_returns_ok_and_audit_log(): void
    {
        $response = $this->withToken($this->agentToken)
            ->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('surface', 'api/v1');

        $this->assertDatabaseCount('agent_request_logs', 1);
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    /** @test */
    public function year_header_overrides_year_context_on_agent_surface(): void
    {
        $this->withToken($this->agentToken)
            ->withHeader('X-Centinela-Year', '2025')
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('year', 2025);
    }

    /** @test */
    public function invalid_year_returns_422_envelope(): void
    {
        $this->withToken($this->agentToken)
            ->getJson('/api/v1/health?year=1999')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'YEAR_NOT_FOUND');
    }

    /** @test */
    public function ip_outside_allowlist_is_denied(): void
    {
        config(['centinela.agent_api.ip_allowlist' => ['10.0.0.0/8']]);

        $this->withToken($this->agentToken)
            ->getJson('/api/v1/health')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'AGENT_IP_DENIED');
    }

    /** @test */
    public function token_without_read_ability_is_forbidden(): void
    {
        $token = $this->agent->createToken('ops-only', ['ops'], now()->addHour())->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/health')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /** @test */
    public function api_user_forbidden_for_agent_role(): void
    {
        $this->withToken($this->agentToken)
            ->getJson('/api/user')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /** @test */
    public function mcp_web_endpoint_uses_agent_stack(): void
    {
        // GET /mcp → 405 (transporte MCP); POST sin auth → envelope 401.
        $this->getJson('/mcp')->assertStatus(405);

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
    public function artisan_issues_and_revokes_read_token_with_ttl(): void
    {
        $this->artisan('centinela:agent:issue-token', [
            'email' => 'nuevo-agente@example.com',
            '--create' => true,
            '--ss' => 1,
            '--ttl' => 30,
            '--name' => 'test-agent',
        ])->assertExitCode(0);

        $user = User::query()->where('email', 'nuevo-agente@example.com')->first();
        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->hasRole('agente'));
        $this->assertSame(1, (int) $user->cen_hie_dependency_id);

        $token = $user->tokens()->first();
        $this->assertNotNull($token);
        $this->assertSame(['read'], $token->abilities);
        $this->assertNotNull($token->expires_at);

        $this->artisan('centinela:agent:revoke-token', [
            '--token-id' => $token->id,
        ])->assertExitCode(0);

        $this->assertNull($user->tokens()->where('id', $token->id)->first());
    }

    /** @test */
    public function prune_request_logs_respects_retention(): void
    {
        $log = new AgentRequestLog([
            'request_id' => (string) Str::uuid(),
            'user_id' => $this->agent->id,
            'method' => 'GET',
            'path' => '/api/v1/health',
            'query_hash' => hash('sha256', ''),
            'status_code' => 200,
            'ip' => '127.0.0.1',
            'latency_ms' => 1,
        ]);
        $log->created_at = now()->subDays(100);
        $log->updated_at = now()->subDays(100);
        $log->save();

        $this->artisan('centinela:agent:prune-request-logs', ['--days' => 90])
            ->assertExitCode(0);

        $this->assertDatabaseCount('agent_request_logs', 0);
    }
}
