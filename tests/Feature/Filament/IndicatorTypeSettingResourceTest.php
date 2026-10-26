<?php

namespace Tests\Feature\Filament;

use App\Enums\IndicatorType\EtapaVigente;
use App\Enums\IndicatorType\TipoEvaluacion;
use App\Filament\Catalogo\Resources\IndicatorTypeSettings\Pages\CreateIndicatorTypeSetting;
use App\Filament\Catalogo\Resources\IndicatorTypeSettings\Pages\EditIndicatorTypeSetting;
use App\Filament\Catalogo\Resources\IndicatorTypeSettings\Pages\ListIndicatorTypeSettings;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Year;
use App\Policies\IndicatorTypeSettingPolicy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class IndicatorTypeSettingResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $referente;

    private User $controlGestion;

    private CenHieDependency $ssA;

    private CenHieDependency $ssB;

    private Year $year;

    private IndicatorType $instrument;

    protected function setUp(): void
    {
        parent::setUp();

        config(['centinela.filament.enabled' => true]);
        Filament::setCurrentPanel('catalogo');

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control de Gestión']);

        $this->ssA = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '24',
            'nombre' => 'SS A',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
        $this->ssB = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => '25',
            'nombre' => 'SS B',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->year = Year::unguarded(fn () => Year::query()->firstOrCreate(
            ['ano' => 2026],
            ['default' => true]
        ));

        $this->instrument = IndicatorType::query()->whereNotNull('route')->first()
            ?? $this->indicatorTypeByRoute('metas-18834');

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'cen_hie_dependency_id' => $this->ssA->id,
        ]);
        $this->referente = User::factory()->create([
            'role_id' => $referenteRole->id,
            'cen_hie_dependency_id' => $this->ssA->id,
        ]);
        $this->controlGestion = User::factory()->create([
            'role_id' => $cgRole->id,
            'cen_hie_dependency_id' => $this->ssA->id,
        ]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->grantIndicatorTypeSettingPermissions($cgRole, ['browse', 'read', 'edit', 'add', 'delete']);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'module.filament_configurar_tipo_indicador_enabled'],
            [
                'display_name' => 'Filament configurar tipo indicador',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 43,
                'group' => 'Modulos',
            ]
        );
    }

    public function test_guest_is_redirected_from_configurar_tipo_indicador_resource(): void
    {
        $this->get('/catalogo/configurar-tipo-indicador')->assertRedirect();
    }

    public function test_referente_without_permissions_cannot_access_panel_when_flag_on(): void
    {
        config(['centinela.filament.configurar_tipo_indicador_enabled' => true]);

        $this->actingAs($this->referente)
            ->get('/configuracion')
            ->assertForbidden();
    }

    public function test_admin_gets_forbidden_on_resource_when_flag_is_off(): void
    {
        config(['centinela.filament.configurar_tipo_indicador_enabled' => false]);

        $this->actingAs($this->admin)
            ->get('/catalogo/configurar-tipo-indicador')
            ->assertForbidden();
    }

    public function test_admin_can_list_when_flag_is_on(): void
    {
        config(['centinela.filament.configurar_tipo_indicador_enabled' => true]);

        $setting = $this->createSettingForSs($this->ssA);

        $this->actingAs($this->admin)
            ->get('/catalogo/configurar-tipo-indicador')
            ->assertOk();

        Livewire::actingAs($this->admin)
            ->test(ListIndicatorTypeSettings::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$setting]);
    }

    public function test_control_gestion_cannot_access_catalogo_panel(): void
    {
        config(['centinela.filament.configurar_tipo_indicador_enabled' => true]);

        $this->createSettingForSs($this->ssA);

        $this->actingAs($this->controlGestion)
            ->get('/catalogo/configurar-tipo-indicador')
            ->assertForbidden();
    }

    public function test_tenancy_hides_settings_from_other_ss(): void
    {
        config(['centinela.filament.configurar_tipo_indicador_enabled' => true]);

        $mine = $this->createSettingForSs($this->ssA);
        $other = $this->createSettingForSs($this->ssB, [
            'indicator_type_id' => $this->instrument->id,
            'year_id' => $this->year->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListIndicatorTypeSettings::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_admin_can_create_setting_when_flag_on(): void
    {
        config(['centinela.filament.configurar_tipo_indicador_enabled' => true]);

        Livewire::actingAs($this->admin)
            ->test(CreateIndicatorTypeSetting::class)
            ->assertOk()
            ->fillForm([
                'indicator_type_id' => $this->instrument->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => $this->ssA->id,
                'etapa_vigente' => EtapaVigente::Cerrado->value,
                'tipo_evaluacion' => TipoEvaluacion::Anual->value,
                'habilitado' => true,
                'panel_minsal' => 'https://example.test/panel',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('indicator_type_settings', [
            'indicator_type_id' => $this->instrument->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => $this->ssA->id,
            'etapa_vigente' => EtapaVigente::Cerrado->value,
            'tipo_evaluacion' => TipoEvaluacion::Anual->value,
            'panel_minsal' => 'https://example.test/panel',
        ]);
    }

    public function test_edit_updates_enums_and_boot_derives_habilitado_for_metas(): void
    {
        config(['centinela.filament.configurar_tipo_indicador_enabled' => true]);

        $metas = IndicatorType::query()->where('route', 'like', 'metas-%')->first()
            ?? $this->indicatorTypeByRoute('metas-18834');

        $setting = IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $metas->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => $this->ssA->id,
            'etapa_vigente' => EtapaVigente::NoIniciado->value,
            'tipo_evaluacion' => TipoEvaluacion::NoDefinido->value,
            'habilitado' => false,
        ]));

        Livewire::actingAs($this->admin)
            ->test(EditIndicatorTypeSetting::class, ['record' => $setting->getKey()])
            ->assertOk()
            ->fillForm([
                'etapa_vigente' => EtapaVigente::Formulacion->value,
                'tipo_evaluacion' => TipoEvaluacion::Semestral->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting->refresh();
        $this->assertSame(EtapaVigente::Formulacion, $setting->etapa_vigente);
        $this->assertSame(TipoEvaluacion::Semestral, $setting->tipo_evaluacion);
        $this->assertTrue($setting->habilitado);
    }

    public function test_create_validation_rejects_invalid_etapa(): void
    {
        config(['centinela.filament.configurar_tipo_indicador_enabled' => true]);

        Livewire::actingAs($this->admin)
            ->test(CreateIndicatorTypeSetting::class)
            ->fillForm([
                'indicator_type_id' => $this->instrument->id,
                'year_id' => $this->year->id,
                'cen_hie_dependency_id' => $this->ssA->id,
                'etapa_vigente' => 'ETAPA_INVALIDA',
                'tipo_evaluacion' => TipoEvaluacion::Anual->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['etapa_vigente']);
    }

    public function test_admin_can_delete_setting_when_flag_on(): void
    {
        config(['centinela.filament.configurar_tipo_indicador_enabled' => true]);

        $setting = $this->createSettingForSs($this->ssA);

        Livewire::actingAs($this->admin)
            ->test(EditIndicatorTypeSetting::class, ['record' => $setting->getKey()])
            ->callAction('delete')
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('indicator_type_settings', ['id' => $setting->id]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function createSettingForSs(CenHieDependency $ss, array $extra = []): IndicatorTypeSetting
    {
        return IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create(array_merge([
            'indicator_type_id' => $this->instrument->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => $ss->id,
            'etapa_vigente' => EtapaVigente::Cerrado->value,
            'tipo_evaluacion' => TipoEvaluacion::NoDefinido->value,
            'habilitado' => false,
        ], $extra)));
    }

    /**
     * @param  list<string>  $actions
     */
    private function grantIndicatorTypeSettingPermissions(Role $role, array $actions): void
    {
        $ids = [];
        foreach ($actions as $action) {
            $ids[] = Permission::firstOrCreate(
                ['key' => $action.'_'.IndicatorTypeSettingPolicy::PERMISSION_TABLE],
                ['table_name' => IndicatorTypeSettingPolicy::PERMISSION_TABLE]
            )->id;
        }

        $role->permissions()->syncWithoutDetaching($ids);
    }
}
