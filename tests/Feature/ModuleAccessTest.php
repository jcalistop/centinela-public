<?php

namespace Tests\Feature;

use App\Helpers\ModuleAccess;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TCG\Voyager\Models\Role;
use TCG\Voyager\Models\Setting;
use Tests\TestCase;

class ModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $cgRole;

    private User $admin;

    private User $cgUser;

    private CenHieDependency $ss;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $this->cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control Gesti&oacute;n']);

        $this->ss = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Test',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);

        $this->cgUser = User::factory()->create([
            'role_id' => $this->cgRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);

        IndicatorType::unguarded(fn () => IndicatorType::updateOrCreate(
            ['route' => 'adp'],
            [
                'clave_indicador' => 'adp',
                'nombre_indicador' => 'ADP',
                'estado' => true,
                'orden' => 1,
            ]
        ));

        IndicatorType::unguarded(fn () => IndicatorType::updateOrCreate(
            ['route' => 'metas-18834'],
            [
                'clave_indicador' => 'Metas Ley 18.834',
                'nombre_indicador' => 'Metas Sanitarias Ley 18.834',
                'estado' => true,
                'orden' => 2,
            ]
        ));

        IndicatorType::unguarded(fn () => IndicatorType::updateOrCreate(
            ['route' => 'voyager.compromisos.index'],
            [
                'clave_indicador' => 'COMGES',
                'nombre_indicador' => 'COMGES',
                'estado' => true,
                'orden' => 0,
            ]
        ));
    }

    public function test_admin_can_access_any_module_when_catalog_active(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(ModuleAccess::canAccess('adp'));
        $this->assertTrue(ModuleAccess::canAccess('comges'));
        $this->assertTrue(ModuleAccess::canAccess('metas-18834'));
    }

    public function test_access_denied_when_indicator_type_disabled_for_all_roles(): void
    {
        IndicatorType::unguarded(fn () => IndicatorType::where('route', 'adp')->update(['estado' => false]));

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'adp',
            'enabled' => true,
        ]);

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);

        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $param = User::factory()->create([
            'role_id' => $paramRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);

        $visualizadorRole = Role::firstOrCreate(['name' => 'visualizador'], ['display_name' => 'Visualizador']);
        $visualizador = User::factory()->create([
            'role_id' => $visualizadorRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);

        foreach ([$this->admin, $param, $this->cgUser, $referente, $visualizador] as $user) {
            $this->actingAs($user);
            ModuleAccess::flushRequestMemo();
            $this->assertFalse(
                ModuleAccess::canAccess('adp'),
                'Capa 1 Off debe denegar a '.$user->role->name
            );
        }
    }

    public function test_control_gestion_denied_when_indicator_type_disabled_even_if_ss_toggle_enabled(): void
    {
        $this->actingAs($this->cgUser);

        IndicatorType::unguarded(fn () => IndicatorType::where('route', 'metas-18834')->update(['estado' => false]));

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]);

        $this->assertFalse(ModuleAccess::canAccess('metas-18834'));
    }

    public function test_referente_denied_when_year_habilitado_false_even_with_estado_active(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);

        $this->actingAs($referente)->withSession(['indicator_year' => $year]);

        $type = IndicatorType::unguarded(fn () => IndicatorType::where('route', 'metas-18834')->firstOrFail());
        IndicatorType::unguarded(fn () => $type->update(['estado' => true]));

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => $this->ss->id,
            'etapa_vigente' => 'NO_INICIADO',
        ]));

        $this->assertFalse(ModuleAccess::canAccess('metas-18834'));
    }

    public function test_referente_denied_when_habilitado_true_but_estado_false(): void
    {
        $year = Year::unguarded(fn () => Year::create(['ano' => 2026, 'default' => 1]));

        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);

        $this->actingAs($referente)->withSession(['indicator_year' => $year]);

        $type = IndicatorType::unguarded(fn () => IndicatorType::where('route', 'metas-18834')->firstOrFail());
        IndicatorType::unguarded(fn () => $type->update(['estado' => false]));

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => true,
        ]);

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $year->id,
            'cen_hie_dependency_id' => $this->ss->id,
            'etapa_vigente' => 'AUTOEVALUACION',
            'habilitado' => true,
        ]));

        $this->assertFalse(ModuleAccess::canAccess('metas-18834'));
    }

    public function test_admin_and_parametrizador_bypass_capa_2_when_catalog_active(): void
    {
        IndicatorType::unguarded(fn () => IndicatorType::where('route', 'metas-18834')->update(['estado' => true]));

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => false,
        ]);

        $this->actingAs($this->admin);
        ModuleAccess::flushRequestMemo();
        $this->assertTrue(ModuleAccess::canAccess('metas-18834'));

        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $param = User::factory()->create([
            'role_id' => $paramRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);

        $this->actingAs($param);
        ModuleAccess::flushRequestMemo();
        $this->assertTrue(ModuleAccess::canAccess('metas-18834'));
    }

    public function test_control_gestion_denied_when_capa_2_toggle_off_and_catalog_active(): void
    {
        $this->actingAs($this->cgUser);

        IndicatorType::unguarded(fn () => IndicatorType::where('route', 'metas-18834')->update(['estado' => true]));

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => false,
        ]);

        $this->assertFalse(ModuleAccess::canAccess('metas-18834'));
    }

    public function test_parametrizador_denied_when_catalog_off_even_if_toggle_off(): void
    {
        $paramRole = Role::firstOrCreate(['name' => 'parametrizador'], ['display_name' => 'Parametrizador']);
        $param = User::factory()->create([
            'role_id' => $paramRole->id,
            'cen_hie_dependency_id' => $this->ss->id,
        ]);

        $this->actingAs($param);

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'metas-18834',
            'enabled' => false,
        ]);

        IndicatorType::unguarded(fn () => IndicatorType::where('route', 'metas-18834')->update(['estado' => false]));

        $this->assertFalse(ModuleAccess::canAccess('metas-18834'));
    }

    public function test_access_granted_when_indicator_type_enabled(): void
    {
        $this->actingAs($this->cgUser);

        $this->assertTrue(ModuleAccess::canAccess('adp'));
    }

    public function test_access_denied_when_settings_module_disabled(): void
    {
        $this->actingAs($this->cgUser);

        $this->upsertSetting('module.soporte_enabled', 'Off');

        $this->assertFalse(ModuleAccess::canAccess('soporte'));
    }

    public function test_access_granted_when_no_global_setting_exists(): void
    {
        $this->actingAs($this->cgUser);

        $this->assertTrue(ModuleAccess::canAccess('manual_usuario'));
    }

    public function test_access_denied_when_ss_is_disabled(): void
    {
        $this->actingAs($this->cgUser);

        CenHieDependency::unguarded(fn () => $this->ss->update(['habilitado' => false]));

        $this->assertFalse(ModuleAccess::canAccess('adp'));
    }

    public function test_access_granted_when_no_toggle_exists_opt_out(): void
    {
        $this->actingAs($this->cgUser);

        $this->assertTrue(ModuleAccess::canAccess('adp'));
    }

    public function test_access_denied_when_toggle_is_disabled(): void
    {
        $this->actingAs($this->cgUser);

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'adp',
            'enabled' => false,
        ]);

        $this->assertFalse(ModuleAccess::canAccess('adp'));
    }

    public function test_access_granted_when_all_conditions_met(): void
    {
        $this->actingAs($this->cgUser);

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'adp',
            'enabled' => true,
        ]);

        $this->assertTrue(ModuleAccess::canAccess('adp'));
    }

    public function test_access_with_explicit_ss_id(): void
    {
        $this->actingAs($this->cgUser);

        ServiceModuleToggle::create([
            'cen_hie_dependency_id' => $this->ss->id,
            'module_key' => 'adp',
            'enabled' => true,
        ]);

        $this->assertTrue(ModuleAccess::canAccess('adp', $this->ss->id));
    }

    public function test_access_denied_when_ss_type_is_not_servicio(): void
    {
        $this->actingAs($this->cgUser);

        $otherSs = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => 'NA01',
            'nombre' => 'No Aplica',
            'tipo_dependencia' => 'OTRO',
            'habilitado' => true,
        ]));

        $this->assertFalse(ModuleAccess::canAccess('adp', $otherSs->id));
    }

    public function test_unauthenticated_user_has_no_access(): void
    {
        $this->assertFalse(ModuleAccess::canAccess('adp'));
    }

    public function test_admin_still_bypasses_non_instrument_modules(): void
    {
        $this->actingAs($this->admin);

        $this->upsertSetting('module.soporte_enabled', 'Off');

        $this->assertTrue(ModuleAccess::canAccess('soporte'));
    }

    private function upsertSetting(string $key, string $value): void
    {
        $parts = explode('.', $key);
        Setting::unguarded(fn () => Setting::updateOrCreate(
            ['key' => $key],
            [
                'display_name' => $key,
                'value' => $value,
                'type' => 'radio_btn',
                'group' => $parts[0] ?? 'module',
                'order' => 1,
            ]
        ));
    }
}
