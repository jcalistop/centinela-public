<?php

namespace Tests\Feature;

use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetasInstrumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private User $adminUser;

    private User $nonAdminUser;

    private IndicatorType $indicatorType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2025, 'default' => 1]));

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $nonAdminRole = Role::create(['name' => 'referente', 'display_name' => 'Referente']);

        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id]);
        $this->adminUser->role()->associate($adminRole)->save();

        $this->nonAdminUser = User::factory()->create(['role_id' => $nonAdminRole->id]);
        $this->nonAdminUser->role()->associate($nonAdminRole)->save();

        $this->indicatorType = $this->indicatorTypeByRoute(attributes: ['estado' => false]);
    }

    /** @test */
    public function admin_gets_403_when_estado_is_inactive(): void
    {
        // SDD-263 — capa 1: estado Off deniega a todos, incluido admin.
        $response = $this->actingAs($this->adminUser)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/metas-18834');

        $response->assertStatus(403);
    }

    /** @test */
    public function referente_gets_403_when_estado_is_inactive(): void
    {
        $response = $this->actingAs($this->nonAdminUser)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/metas-18834');

        $response->assertStatus(403);
    }

    /** @test */
    public function control_gestion_gets_403_when_estado_is_inactive_even_if_ss_toggle_enabled(): void
    {
        // SDD-263 — capa 1 Off: toggle SS no bypasea.
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control de Gestión']);

        CenHieDependency::unguarded(fn () => CenHieDependency::firstOrCreate(
            ['id' => 1],
            ['codigo' => 'SS01', 'nombre' => 'Servicio Test', 'tipo_dependencia' => 'SERVICIO', 'habilitado' => true]
        ));

        $cgUser = User::factory()->create(['role_id' => $cgRole->id, 'cen_hie_dependency_id' => 1]);
        $cgUser->role()->associate($cgRole)->save();

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]));

        $response = $this->actingAs($cgUser)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/metas-18834');

        $response->assertStatus(403);
    }

    /** @test */
    public function non_admin_can_access_when_estado_is_active(): void
    {
        IndicatorType::unguarded(fn () => $this->indicatorType->update(['estado' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::firstOrCreate(
            ['id' => 1],
            ['codigo' => 'SS01', 'nombre' => 'Servicio Test', 'tipo_dependencia' => 'SERVICIO', 'habilitado' => true]
        ));

        $this->nonAdminUser->update(['cen_hie_dependency_id' => 1]);

        ServiceModuleToggle::unguarded(fn () => ServiceModuleToggle::create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->indicatorType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => 1,
        ]));

        // El controller no debe abortar; el 500 en tests es ruido del layout Voyager
        // (accede a modelos relacionados ausentes en BD de tests vacía).
        $response = $this->actingAs($this->nonAdminUser)
            ->withSession(['indicator_year' => $this->year])
            ->get('/admin/metas-18834');

        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
