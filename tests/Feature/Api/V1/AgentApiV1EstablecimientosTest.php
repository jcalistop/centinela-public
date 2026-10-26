<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Cen\CenCenterType;
use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Models\CenCenter;
use App\Models\CenHieDependency;
use App\Models\Role;
use App\Models\User;
use App\Models\Year;
use App\Support\YearContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentApiV1EstablecimientosTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private User $agent;

    private CenCenter $sarVigente;

    private CenCenter $cesfamVigente;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.agent_api.enabled' => true,
            'centinela.agent_api.ip_allowlist' => [],
        ]);

        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));
        app(YearContext::class)->setCurrent($year);

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

        $role = Role::firstOrCreate(['name' => 'agente'], ['display_name' => 'Agente']);
        $this->agent = User::factory()->create([
            'email' => 'agente-estab@example.com',
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]);
        $this->agent->role()->associate($role)->save();
        $this->token = $this->agent->createToken('agent', ['read'], now()->addHour())->plainTextToken;

        $this->sarVigente = $this->makeCenter(
            'SAR-01',
            'SAR Vigente',
            1,
            CenCenterType::Case45->value,
            CentroEstadoFuncionamiento::VigenteEnOperacionHabitual->value,
        );

        $this->makeCenter(
            'SAR-02',
            'SAR Cerrado',
            1,
            CenCenterType::Case45->value,
            CentroEstadoFuncionamiento::Cerrado->value,
        );

        $this->cesfamVigente = $this->makeCenter(
            'CES-01',
            'CESFAM Vigente',
            1,
            CenCenterType::Case16->value,
            CentroEstadoFuncionamiento::VigenteEnOperacionTransitoria->value,
        );

        $this->makeCenter(
            'SAR-OTH',
            'SAR Otro SS',
            2,
            CenCenterType::Case45->value,
            CentroEstadoFuncionamiento::VigenteEnOperacionHabitual->value,
        );
    }

    /** @test */
    public function lists_establecimientos_of_token_ss_with_dto_fields(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/establecimientos')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->sarVigente->id, $ids);
        $this->assertContains($this->cesfamVigente->id, $ids);

        $item = collect($response->json('data'))->firstWhere('id', $this->sarVigente->id);
        $this->assertSame('SAR-01', $item['codigo']);
        $this->assertSame(CenCenterType::Case45->value, $item['tipo']);
        $this->assertArrayHasKey('updated_at', $item);
        $this->assertArrayHasKey('synced_at', $item);
        $this->assertArrayHasKey('latitud', $item);
        $this->assertArrayNotHasKey('tarea_pendiente', $item);
    }

    /** @test */
    public function filters_sar_vigente_and_exposes_total(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/establecimientos?tipo=SAR&vigente=true')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.codigo', 'SAR-01');
    }

    /** @test */
    public function show_returns_ficha_and_hides_other_ss(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/establecimientos/'.$this->sarVigente->id)
            ->assertOk()
            ->assertJsonPath('data.codigo', 'SAR-01')
            ->assertJsonPath('data.nombre_corto', 'SAR Vigente');

        $other = CenCenter::query()->where('codigo', 'SAR-OTH')->firstOrFail();
        $this->withToken($this->token)
            ->getJson('/api/v1/establecimientos/'.$other->id)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    /** @test */
    public function invalid_tipo_returns_422(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/establecimientos?tipo=NOEXISTE')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /** @test */
    public function rejects_unauthenticated_and_flag_off(): void
    {
        $this->getJson('/api/v1/establecimientos')
            ->assertUnauthorized();

        config(['centinela.agent_api.enabled' => false]);
        $this->withToken($this->token)
            ->getJson('/api/v1/establecimientos')
            ->assertForbidden();
    }

    private function makeCenter(
        string $codigo,
        string $nombreCorto,
        int $ssId,
        string $tipo,
        string $estado,
    ): CenCenter {
        return CenCenter::unguarded(fn () => CenCenter::create([
            'codigo' => $codigo,
            'cod_antiguo' => null,
            'codigo_madre' => null,
            'cod_madre_antiguo' => null,
            'cen_hie_dependency_id' => $ssId,
            'pertenencia_snss' => 'Perteneciente',
            'nombre_oficial' => $nombreCorto.' Oficial',
            'nombre_corto' => $nombreCorto,
            'cen_type' => $tipo,
            'cen_scope' => 'Establecimiento de Salud',
            'cen_certification' => 'No Aplica',
            'cen_adm_dependency' => 'Municipal',
            'nivel_atencion' => 'Primario',
            'tiene_urgencia' => 'No',
            'cen_emergency_type' => 'No Aplica',
            'tipo_sapu' => 'No Aplica',
            'cen_provider_type' => 'Público',
            'estado_funcionamiento' => $estado,
            'nivel_complejidad' => 'Baja',
            'tipo_atencion' => 'Abierta',
            'synced_at' => now()->subDay(),
        ]));
    }
}
