<?php

namespace Tests\Feature\Support;

use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\Support\SupportTicketInstrumentOptionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class SupportTicketInstrumentOptionsTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private IndicatorType $comgesType;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('centinela.support.enabled', true);

        $this->year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Uno',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->comgesType = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'comges',
            'nombre_indicador' => 'Compromiso de Gestión',
            'route' => 'comges',
            'estado' => true,
            'orden' => 1,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $this->comgesType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'habilitado' => true,
        ]));
    }

    private function controlGestionUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gestión']);
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null],
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = User::unguarded(fn () => User::create([
            'name' => 'CG Instrumentos',
            'email' => 'cg-instrumentos@test.local',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'cen_hie_dependency_id' => 1,
        ]));
        $user->role()->associate($role)->save();

        return $user;
    }

    public function test_create_form_lists_only_instruments_enabled_for_servicio_salud(): void
    {
        $disabledType = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'metas-18834',
            'nombre_indicador' => 'Metas 18834',
            'route' => 'metas-18834',
            'estado' => true,
            'orden' => 2,
        ]));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $disabledType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'habilitado' => true,
        ]));

        ServiceModuleToggle::query()->create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'metas-18834',
            'enabled' => false,
        ]);

        $user = $this->controlGestionUser();

        $this->actingAs($user)->withSession(['indicator_year' => $this->year]);

        $available = app(SupportTicketInstrumentOptionsService::class)->forServicioSalud(1, $user);

        $this->assertTrue($available->contains('id', $this->comgesType->id));
        $this->assertFalse($available->contains('id', $disabledType->id));

        $response = $this->get(route('centinela.support.create'));
        $response->assertOk();
        $response->assertSee('Compromiso de Gestión', false);
        $response->assertDontSee('Metas 18834', false);
    }

    public function test_store_rejects_instrument_not_enabled_for_servicio_salud(): void
    {
        $disabledType = IndicatorType::unguarded(fn () => IndicatorType::firstOrCreate(
            ['clave_indicador' => 'metas-20707'],
            [
                'nombre_indicador' => 'Metas 20707',
                'route' => 'metas-20707',
                'estado' => true,
                'orden' => 3,
            ]
        ));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::firstOrCreate(
            [
                'indicator_type_id' => $disabledType->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => 1,
            ],
            ['habilitado' => true],
        ));

        ServiceModuleToggle::query()->updateOrCreate(
            [
                'cen_hie_dependency_id' => 1,
                'module_key' => 'metas-20707',
            ],
            ['enabled' => false],
        );

        $user = $this->controlGestionUser();

        $this->actingAs($user)->withSession(['indicator_year' => $this->year]);

        $this->post(route('centinela.support.store'), [
            'subject' => 'Ticket con instrumento inválido',
            'body' => 'Detalle del ticket.',
            'area' => 'gestion',
            'indicator_type_id' => $disabledType->id,
        ])->assertSessionHasErrors('indicator_type_id');
    }

    public function test_metas_appears_for_control_gestion_when_ss_toggle_enabled_without_year_habilitado(): void
    {
        // SDD-263 — capa 1 On; el caso prueba que toggle SS basta sin habilitado anual.
        $metasType = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => 'metas-18834',
            'nombre_indicador' => 'Metas 18834',
            'route' => 'metas-18834',
            'estado' => true,
            'orden' => 2,
        ]));

        ServiceModuleToggle::query()->create([
            'cen_hie_dependency_id' => 1,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $metasType->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => 1,
            'habilitado' => false,
        ]));

        $user = $this->controlGestionUser();

        $this->actingAs($user)->withSession(['indicator_year' => $this->year]);

        $available = app(SupportTicketInstrumentOptionsService::class)->forServicioSalud(1, $user);

        $this->assertTrue(
            $available->contains('id', $metasType->id),
            'Metas debe listarse con toggle SS activo aunque habilitado anual sea false.',
        );

        $response = $this->get(route('centinela.support.create'));
        $response->assertOk();
        $response->assertSee('Metas 18834', false);
    }

    public function test_instruments_endpoint_returns_enabled_instruments_for_admin_ss(): void
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_admin', 'table_name' => null],
            ['key' => 'browse_admin', 'table_name' => null],
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $admin = User::factory()->create(['role_id' => $role->id, 'cen_hie_dependency_id' => 1]);
        $admin->role()->associate($role)->save();

        $this->actingAs($admin)->withSession(['indicator_year' => $this->year]);

        $response = $this->getJson(route('centinela.support.instruments.available', ['ss_id' => 1]));

        $response->assertOk();
        $response->assertJsonFragment(['label' => 'Compromiso de Gestión']);
    }
}
