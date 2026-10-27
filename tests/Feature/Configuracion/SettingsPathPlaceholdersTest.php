<?php

declare(strict_types=1);

namespace Tests\Feature\Configuracion;

use App\Livewire\Configuracion\SettingsManager;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use TCG\Voyager\Models\Permission;
use TCG\Voyager\Models\Setting;
use Tests\TestCase;

class SettingsPathPlaceholdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_git_and_mysqldump_placeholders_stay_empty_even_if_details_have_example_paths(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $perm = Permission::firstOrCreate(
            ['key' => 'browse_settings', 'table_name' => null],
            ['key' => 'browse_settings', 'table_name' => null]
        );
        $adminRole->permissions()->syncWithoutDetaching([$perm->id]);

        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $admin->role()->associate($adminRole)->save();

        Setting::query()->updateOrCreate(['key' => 'services.git_binary_path'], [
            'display_name' => 'Ruta al binario de Git',
            'value' => '',
            'details' => json_encode([
                'description' => 'Ruta absoluta o comando del binario de Git.',
                'placeholder' => 'C:\\ruta\\a\\Git\\cmd\\git.exe',
            ]),
            'type' => 'text',
            'order' => 4,
            'group' => 'Deploy',
        ]);

        Setting::query()->updateOrCreate(['key' => 'services.db_dump_path'], [
            'display_name' => 'Ruta al binario mysqldump',
            'value' => '',
            'details' => json_encode([
                'description' => 'Ruta absoluta al binario mysqldump.',
                'placeholder' => 'C:\\ruta\\a\\mysql\\bin',
            ]),
            'type' => 'text',
            'order' => 5,
            'group' => 'Sistema',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(SettingsManager::class)
            ->assertOk();

        $manager = $component->instance();
        $this->assertInstanceOf(SettingsManager::class, $manager);

        $this->assertSame('', $manager->textPlaceholder('services.git_binary_path', [
            'placeholder' => 'C:\\ruta\\a\\Git\\cmd\\git.exe',
        ]));
        $this->assertSame('', $manager->textPlaceholder('services.db_dump_path', [
            'placeholder' => 'C:\\ruta\\a\\mysql\\bin',
        ]));

        $html = $component->html();
        $this->assertStringNotContainsString('C:\\ruta\\a\\Git\\cmd\\git.exe', $html);
        $this->assertStringNotContainsString('C:\\ruta\\a\\mysql\\bin', $html);
        $this->assertMatchesRegularExpression(
            '/wire:model\.lazy="settings\.services_git_binary_path"[^>]*placeholder=""/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/wire:model\.lazy="settings\.services_db_dump_path"[^>]*placeholder=""/',
            $html
        );
    }
}
