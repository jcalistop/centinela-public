<?php

namespace Tests\Feature\Filament;

use App\Filament\Configuracion\Pages\ManageSystemSettings;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Configuration\ConfigurationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageSystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $referente;

    protected function setUp(): void
    {
        parent::setUp();

        config(['centinela.filament.enabled' => true]);
        Filament::setCurrentPanel('configuracion');

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->referente = User::factory()->create(['role_id' => $referenteRole->id]);

        $this->seedPilotSettings();
    }

    public function test_guest_is_redirected_from_filament_settings(): void
    {
        $this->get('/configuracion/settings')->assertRedirect();
    }

    public function test_non_admin_cannot_access_configuracion_panel(): void
    {
        config(['centinela.filament.settings_enabled' => true]);

        $this->actingAs($this->referente)
            ->get('/configuracion')
            ->assertForbidden();
    }

    public function test_master_off_returns_not_found(): void
    {
        config(['centinela.filament.enabled' => false]);

        $this->actingAs($this->admin)
            ->get('/configuracion/settings')
            ->assertNotFound();
    }

    public function test_admin_gets_forbidden_when_surface_flag_is_off(): void
    {
        config(['centinela.filament.settings_enabled' => false]);

        $this->actingAs($this->admin)
            ->get('/configuracion/settings')
            ->assertForbidden();
    }

    public function test_admin_can_view_settings_when_flag_is_on(): void
    {
        config(['centinela.filament.settings_enabled' => true]);

        $this->actingAs($this->admin)
            ->get('/configuracion/settings')
            ->assertOk();
    }

    public function test_admin_can_save_setting_per_semantic_group(): void
    {
        config(['centinela.filament.settings_enabled' => true]);

        Livewire::actingAs($this->admin)
            ->test(ManageSystemSettings::class)
            ->assertOk()
            ->fillForm([
                'admin_title' => 'Centinela Filament',
                'module_usage_tracking_enabled' => 'Off',
                'notifications_contact_mail_to' => 'ops@example.test',
                'system_dashboard_cache_ttl' => '120',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Centinela Filament', SystemSetting::query()->where('key', 'admin.title')->value('value'));
        $this->assertSame('Off', SystemSetting::query()->where('key', 'module.usage_tracking_enabled')->value('value'));
        $this->assertSame('ops@example.test', SystemSetting::query()->where('key', 'notifications.contact_mail_to')->value('value'));
        $this->assertSame('120', SystemSetting::query()->where('key', 'system.dashboard_cache_ttl')->value('value'));

        $configuration = app(ConfigurationService::class);
        $configuration->invalidate();
        $this->assertSame('Off', $configuration->get('module.usage_tracking_enabled'));
        $this->assertSame('ops@example.test', $configuration->get('notifications.contact_mail_to'));
    }

    public function test_invalid_radio_value_does_not_corrupt_other_keys(): void
    {
        config(['centinela.filament.settings_enabled' => true]);

        SystemSetting::query()->where('key', 'admin.title')->update(['value' => 'Original']);

        Livewire::actingAs($this->admin)
            ->test(ManageSystemSettings::class)
            ->fillForm([
                'admin_title' => 'Should Not Persist',
                'module_usage_tracking_enabled' => 'Maybe',
            ])
            ->call('save');

        $this->assertSame('Original', SystemSetting::query()->where('key', 'admin.title')->value('value'));
        $this->assertSame('On', SystemSetting::query()->where('key', 'module.usage_tracking_enabled')->value('value'));
    }

    private function seedPilotSettings(): void
    {
        $rows = [
            [
                'key' => 'admin.title',
                'display_name' => 'Título admin',
                'value' => 'Centinela',
                'details' => null,
                'type' => 'text',
                'order' => 1,
                'group' => 'Admin',
            ],
            [
                'key' => 'module.usage_tracking_enabled',
                'display_name' => 'Usage tracking',
                'value' => 'On',
                'details' => '{"default":"On","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 2,
                'group' => 'Modulos',
            ],
            [
                'key' => 'module.filament_settings_enabled',
                'display_name' => 'Filament settings',
                'value' => 'Off',
                'details' => '{"default":"Off","options":{"On":"Activado","Off":"Desactivado"}}',
                'type' => 'radio_btn',
                'order' => 3,
                'group' => 'Modulos',
            ],
            [
                'key' => 'notifications.contact_mail_to',
                'display_name' => 'Contact mail',
                'value' => 'old@example.test',
                'details' => null,
                'type' => 'text',
                'order' => 4,
                'group' => 'Notificaciones',
            ],
            [
                'key' => 'system.dashboard_cache_ttl',
                'display_name' => 'Dashboard cache TTL',
                'value' => '60',
                'details' => null,
                'type' => 'text',
                'order' => 5,
                'group' => 'Sistema',
            ],
        ];

        foreach ($rows as $row) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $row['key']],
                $row
            );
        }
    }
}
