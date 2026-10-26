<?php

namespace Tests\Feature\Filament;

use App\Filament\Catalogo\Resources\IndicatorTypes\Pages\CreateIndicatorType;
use App\Filament\Catalogo\Resources\IndicatorTypes\Pages\EditIndicatorType;
use App\Filament\Catalogo\Resources\IndicatorTypes\Pages\ListIndicatorTypes;
use App\Models\IndicatorType;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Policies\IndicatorTypePolicy;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use TCG\Voyager\Models\Permission;
use Tests\TestCase;

class IndicatorTypeResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $referente;

    private User $controlGestion;

    protected function setUp(): void
    {
        parent::setUp();

        config(['centinela.filament.enabled' => true]);
        Filament::setCurrentPanel('catalogo');

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);
        $cgRole = Role::firstOrCreate(['name' => 'control-gestion'], ['display_name' => 'Control de Gestión']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->referente = User::factory()->create(['role_id' => $referenteRole->id]);
        $this->controlGestion = User::factory()->create(['role_id' => $cgRole->id]);
        $this->controlGestion->role()->associate($cgRole)->save();

        $this->grantIndicatorTypePermissions($cgRole, ['browse', 'read', 'edit', 'add']);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'module.filament_instrumentos_evaluacion_enabled'],
            [
                'display_name' => 'Filament instrumentos',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 41,
                'group' => 'Modulos',
            ]
        );
    }

    public function test_guest_is_redirected_from_instrumentos_resource(): void
    {
        $this->get('/catalogo/instrumentos-evaluacion')->assertRedirect();
    }

    public function test_referente_without_permissions_cannot_access_panel_when_flag_on(): void
    {
        config(['centinela.filament.instrumentos_evaluacion_enabled' => true]);

        $this->actingAs($this->referente)
            ->get('/configuracion')
            ->assertForbidden();
    }

    public function test_admin_gets_forbidden_on_resource_when_flag_is_off(): void
    {
        config(['centinela.filament.instrumentos_evaluacion_enabled' => false]);

        $this->actingAs($this->admin)
            ->get('/catalogo/instrumentos-evaluacion')
            ->assertForbidden();
    }

    public function test_admin_can_list_when_flag_is_on(): void
    {
        config(['centinela.filament.instrumentos_evaluacion_enabled' => true]);

        $this->assertGreaterThan(0, IndicatorType::query()->count());

        $this->actingAs($this->admin)
            ->get('/catalogo/instrumentos-evaluacion')
            ->assertOk();

        Livewire::actingAs($this->admin)
            ->test(ListIndicatorTypes::class)
            ->assertOk()
            ->assertCanSeeTableRecords(IndicatorType::query()->limit(5)->get());
    }

    public function test_control_gestion_cannot_access_catalogo_panel(): void
    {
        config(['centinela.filament.instrumentos_evaluacion_enabled' => true]);

        $this->actingAs($this->controlGestion)
            ->get('/catalogo/instrumentos-evaluacion')
            ->assertForbidden();
    }

    public function test_admin_can_create_instrument_when_flag_on(): void
    {
        config(['centinela.filament.instrumentos_evaluacion_enabled' => true]);
        Cache::put('indicator_type_routes', ['stale' => true], 60);

        $freed = IndicatorType::query()->where('route', 'adp')->first()
            ?? IndicatorType::query()->orderByDesc('id')->first();
        $this->assertNotNull($freed);
        $available = $freed->route;
        $freed->delete();

        Livewire::actingAs($this->admin)
            ->test(CreateIndicatorType::class)
            ->assertOk()
            ->fillForm([
                'clave_indicador' => 'T2a Clave Create',
                'nombre_indicador' => 'T2a Nombre Create',
                'descripcion_indicador' => 'Prueba T2a',
                'route' => $available,
                'icon_class' => 'voyager-compass',
                'measurement_granularity' => 'establishment',
                'estado' => true,
                'orden' => 90,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(EditIndicatorType::getUrl(['record' => $available]));

        $this->assertDatabaseHas('indicator_types', [
            'clave_indicador' => 'T2a Clave Create',
            'route' => $available,
            'view' => null,
        ]);
        $this->assertFalse(Cache::has('indicator_type_routes'));

        $this->actingAs($this->admin)
            ->get(EditIndicatorType::getUrl(['record' => $available]))
            ->assertOk();
    }

    public function test_edit_keeps_route_immutable(): void
    {
        config(['centinela.filament.instrumentos_evaluacion_enabled' => true]);

        $type = IndicatorType::query()->whereNotNull('route')->first();
        $this->assertNotNull($type);
        $originalRoute = $type->route;

        Livewire::actingAs($this->admin)
            ->test(EditIndicatorType::class, ['record' => $type->getRouteKey()])
            ->assertOk()
            ->assertFormFieldIsDisabled('route')
            ->fillForm([
                'nombre_indicador' => 'T2a Nombre Edit actualizado',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $type->refresh();
        $this->assertSame($originalRoute, $type->route);
        $this->assertSame('T2a Nombre Edit actualizado', $type->nombre_indicador);
    }

    public function test_create_validation_rejects_invalid_route(): void
    {
        config(['centinela.filament.instrumentos_evaluacion_enabled' => true]);

        Livewire::actingAs($this->admin)
            ->test(CreateIndicatorType::class)
            ->fillForm([
                'clave_indicador' => 'T2a Clave Invalid',
                'nombre_indicador' => 'T2a Nombre Invalid',
                'route' => 'no-existe',
                'measurement_granularity' => 'establishment',
                'estado' => false,
            ])
            ->call('create')
            ->assertHasFormErrors(['route']);
    }

    public function test_create_route_select_lists_all_config_slugs_even_when_in_use(): void
    {
        config(['centinela.filament.instrumentos_evaluacion_enabled' => true]);

        $slugs = IndicatorType::configMetasRouteSlugs();
        $this->assertNotEmpty($slugs);

        foreach ($slugs as $index => $slug) {
            if (IndicatorType::query()->where('route', $slug)->exists()) {
                continue;
            }

            IndicatorType::query()->create([
                'clave_indicador' => "T2a occupy {$index}",
                'nombre_indicador' => "T2a Occupy {$index}",
                'descripcion_indicador' => null,
                'icon_class' => 'voyager-book',
                'route' => $slug,
                'view' => null,
                'estado' => false,
                'orden' => 100 + $index,
                'measurement_granularity' => 'establishment',
            ]);
        }

        $livewire = Livewire::actingAs($this->admin)
            ->test(CreateIndicatorType::class)
            ->assertOk();

        $component = $livewire->instance()->getSchemaComponent('form.route');
        $this->assertInstanceOf(Select::class, $component);

        /** @var Select $component */
        $options = $component->getOptions();
        $this->assertNotEmpty($options, 'El select de route no debe quedar sin opciones cuando todos los slugs están en uso.');

        foreach ($slugs as $slug) {
            $this->assertArrayHasKey($slug, $options);
            $this->assertStringContainsString($slug, (string) $options[$slug]);
            $this->assertTrue(
                $component->isOptionDisabled($slug, (string) $options[$slug]),
                "El slug en uso «{$slug}» debe estar deshabilitado."
            );
        }
    }

    /**
     * @param  list<string>  $actions
     */
    private function grantIndicatorTypePermissions(Role $role, array $actions): void
    {
        $ids = [];
        foreach ($actions as $action) {
            $ids[] = Permission::firstOrCreate(
                ['key' => $action.'_'.IndicatorTypePolicy::PERMISSION_TABLE],
                ['table_name' => IndicatorTypePolicy::PERMISSION_TABLE]
            )->id;
        }

        $role->permissions()->syncWithoutDetaching($ids);
    }
}
