<?php

namespace Tests\Feature\Filament;

use App\Filament\Configuracion\Pages\ManageSystemSettings;
use App\Livewire\Configuracion\SettingsManager;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Configuration\FilamentChasisGate;
use App\Support\Configuration\ProductClosedSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use TCG\Voyager\Models\Permission;
use TCG\Voyager\Models\Setting;
use Tests\TestCase;

class FilamentProductClosedTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_settings', 'table_name' => null],
            ['key' => 'browse_settings', 'table_name' => null]
        );
        $adminRole->permissions()->syncWithoutDetaching([$perm->id]);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->admin->role()->associate($adminRole)->save();
    }

    public function test_product_closed_filament_urls_return_not_found(): void
    {
        $paths = [
            '/servicio-salud',
            '/catalogo',
            '/pais',
            '/configuracion',
            '/servicio-salud/login',
            '/filament',
            '/filament/comges/compromisos',
            '/configuracion/paneles-publicos',
            '/configuracion/mensajeria',
            '/filament/public-info-panels/contrato-agentes-paneles.md',
        ];

        foreach ($paths as $path) {
            $this->actingAs($this->admin)
                ->get($path)
                ->assertNotFound();
        }

        $this->get('/servicio-salud/login')->assertNotFound();
        $this->get('/filament')->assertNotFound();
    }

    public function test_runtime_enabled_ignores_config(): void
    {
        config(['centinela.filament.enabled' => true]);

        $this->assertFalse(FilamentChasisGate::runtimeEnabled());
        $this->assertTrue(FilamentChasisGate::enabled());
    }

    public function test_env_example_has_no_filament_keys(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertStringNotContainsString('CENTINELA_FILAMENT_', $example);
        $this->assertStringContainsString('CENTINELA_MESSAGING_WAVE_SIZE', $example);
        $this->assertStringContainsString('CENTINELA_AGENT_API_ENABLED', $example);
    }

    public function test_config_keeps_filament_keys(): void
    {
        $filament = config('centinela.filament');
        $this->assertIsArray($filament);
        $this->assertArrayHasKey('enabled', $filament);
        $this->assertArrayHasKey('settings_enabled', $filament);
        $this->assertArrayHasKey('instrumentos_evaluacion_enabled', $filament);
        $this->assertArrayHasKey('establecimientos_enabled', $filament);
        $this->assertArrayHasKey('configurar_tipo_indicador_enabled', $filament);
        $this->assertArrayHasKey('comges_browse_enabled', $filament);
        $this->assertArrayHasKey('messaging_enabled', $filament);
    }

    public function test_voyager_settings_hides_filament_toggles_and_keeps_guest_flags(): void
    {
        $this->seedSettingsRows();

        $component = Livewire::actingAs($this->admin)
            ->test(SettingsManager::class)
            ->assertOk();

        $keys = collect($component->get('settingsByGroup'))
            ->flatten(1)
            ->pluck('key');

        $this->assertFalse(
            $keys->contains(fn (string $key): bool => ProductClosedSettings::isHiddenKey($key))
        );
        $this->assertTrue($keys->contains('module.public_dashboard_enabled'));
    }

    public function test_filament_settings_hides_filament_toggles_when_chasis_forced_on(): void
    {
        config([
            'centinela.filament.enabled' => true,
            'centinela.filament.settings_enabled' => true,
        ]);
        Filament::setCurrentPanel('configuracion');
        $this->seedSettingsRows();

        Livewire::actingAs($this->admin)
            ->test(ManageSystemSettings::class)
            ->assertOk()
            ->assertDontSee('module.filament_enabled', false)
            ->assertDontSee('module.filament_settings_enabled', false)
            ->assertDontSee('admin.voyager_compass_enabled', false)
            ->assertDontSee('module.chat_ia_enabled', false)
            ->assertDontSee('module.user_manual_enabled', false)
            ->assertDontSee('module.whatsapp_enabled', false)
            ->assertDontSee('notifications.whatsapp_alert_to', false)
            ->assertSee('module.public_dashboard_enabled', false);
    }

    private function seedSettingsRows(): void
    {
        $rows = [
            [
                'key' => 'module.filament_enabled',
                'display_name' => 'Filament maestro',
                'value' => 'On',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 1,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.filament_settings_enabled',
                'display_name' => 'Filament settings',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 2,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.public_dashboard_enabled',
                'display_name' => 'Dashboard público',
                'value' => 'On',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 3,
                'group' => 'Modulos',
            ],
            [
                'key' => 'admin.voyager_compass_enabled',
                'display_name' => 'Habilitar Compass de Voyager',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 4,
                'group' => 'Admin',
            ],
            [
                'key' => 'module.chat_ia_enabled',
                'display_name' => 'Chat IA',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 5,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.user_manual_enabled',
                'display_name' => 'Manual de usuario',
                'value' => 'On',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 6,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.whatsapp_enabled',
                'display_name' => 'WhatsApp',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 7,
                'group' => 'Modulos',
            ],
            [
                'key' => 'notifications.whatsapp_alert_to',
                'display_name' => 'WhatsApp alertas',
                'value' => 'alert@example.test',
                'details' => null,
                'type' => 'text',
                'order' => 8,
                'group' => 'Notificaciones',
            ],
        ];

        foreach ($rows as $row) {
            Setting::query()->updateOrCreate(['key' => $row['key']], $row);
            SystemSetting::query()->updateOrCreate(['key' => $row['key']], $row);
        }
    }
}
