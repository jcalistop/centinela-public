<?php

namespace Tests\Feature\Filament;

use App\Filament\Configuracion\Resources\PublicInfoPanels\Pages\ListPublicInfoPanels;
use App\Models\PublicInfoPanel;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PublicInfoPanelPublishToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $referente;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.filament.enabled' => true,
            'centinela.filament.settings_enabled' => true,
        ]);
        Filament::setCurrentPanel('configuracion');

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $referenteRole = Role::firstOrCreate(['name' => 'referente'], ['display_name' => 'Referente']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->referente = User::factory()->create(['role_id' => $referenteRole->id]);
    }

    public function test_publishes_html_bundle_with_required_assets(): void
    {
        $panel = $this->createBundlePanel();

        $this->toggle($panel, true);

        $this->assertTrue($panel->fresh()->publicado);
    }

    public function test_publishes_allowlisted_embed(): void
    {
        $panel = $this->createPanel([
            'slug' => 'panel-embed',
            'tipo' => PublicInfoPanel::TIPO_EMBED_EXTERNO,
            'embed_url' => 'https://app.powerbi.com/view?r=eyDemo',
            'preview_png_path' => $this->storePreview('panel-embed'),
            'html_path' => null,
            'csv_path' => null,
        ]);

        $this->toggle($panel, true);

        $this->assertTrue($panel->fresh()->publicado);
    }

    public function test_blocks_publish_without_preview_png(): void
    {
        $panel = $this->createPanel([
            'slug' => 'sin-preview',
            'html_path' => $this->storeAsset('sin-preview/panel.html', '<html><body>ok</body></html>'),
            'csv_path' => $this->storeAsset('sin-preview/datos.csv', "a,b\n1,2\n"),
        ]);

        $this->assertRejected($panel, 'PNG');
    }

    public function test_blocks_html_bundle_without_csv(): void
    {
        $panel = $this->createPanel([
            'slug' => 'sin-csv',
            'html_path' => $this->storeAsset('sin-csv/panel.html', '<html><body>ok</body></html>'),
            'preview_png_path' => $this->storePreview('sin-csv'),
        ]);

        $this->assertRejected($panel, 'HTML, CSV y PNG');
    }

    public function test_blocks_embed_outside_allowlist(): void
    {
        $panel = $this->createPanel([
            'slug' => 'embed-prohibido',
            'tipo' => PublicInfoPanel::TIPO_EMBED_EXTERNO,
            'embed_url' => 'https://ejemplo-no-permitido.test/view',
            'preview_png_path' => $this->storePreview('embed-prohibido'),
            'html_path' => null,
            'csv_path' => null,
        ]);

        $this->assertRejected($panel, 'allowlist');
    }

    public function test_blocks_publish_when_html_has_scan_violations(): void
    {
        $html = '<html><head><script src="https://cdn.example.com/lib.js"></script></head><body>ok</body></html>';

        $panel = $this->createPanel([
            'slug' => 'html-inseguro',
            'html_path' => $this->storeAsset('html-inseguro/panel.html', $html),
            'csv_path' => $this->storeAsset('html-inseguro/datos.csv', "a,b\n1,2\n"),
            'preview_png_path' => $this->storePreview('html-inseguro'),
        ]);

        $this->assertRejected($panel, 'scripts con src externo');
    }

    public function test_unpublishing_is_always_allowed(): void
    {
        $panel = $this->createPanel([
            'slug' => 'publicado-sin-assets',
            'publicado' => true,
        ]);

        $this->toggle($panel, false);

        $this->assertFalse($panel->fresh()->publicado);
    }

    public function test_toggle_is_disabled_for_non_admin(): void
    {
        $panel = $this->createBundlePanel();

        $livewire = Livewire::actingAs($this->referente)->test(ListPublicInfoPanels::class);

        $livewire->call('updateTableColumnState', 'publicado', $panel->getKey(), true);

        $this->assertFalse($panel->fresh()->publicado);
    }

    public function test_list_renders_published_column_as_toggle(): void
    {
        $this->createBundlePanel();

        $this->actingAs($this->admin)
            ->get(ListPublicInfoPanels::getUrl())
            ->assertOk()
            ->assertSee('fi-ta-toggle', false);
    }

    private function toggle(PublicInfoPanel $panel, bool $state): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListPublicInfoPanels::class)
            ->call('updateTableColumnState', 'publicado', $panel->getKey(), $state);
    }

    private function assertRejected(PublicInfoPanel $panel, string $expectedMessageFragment): void
    {
        Livewire::actingAs($this->admin)
            ->test(ListPublicInfoPanels::class)
            ->call('updateTableColumnState', 'publicado', $panel->getKey(), true)
            ->assertReturned(function (mixed $value) use ($expectedMessageFragment): bool {
                return is_array($value)
                    && isset($value['error'])
                    && str_contains((string) $value['error'], $expectedMessageFragment);
            });

        $this->assertFalse($panel->fresh()->publicado);
    }

    private function createBundlePanel(): PublicInfoPanel
    {
        return $this->createPanel([
            'slug' => 'bundle-completo',
            'html_path' => $this->storeAsset('bundle-completo/panel.html', '<html><body>ok</body></html>'),
            'csv_path' => $this->storeAsset('bundle-completo/datos.csv', "a,b\n1,2\n"),
            'preview_png_path' => $this->storePreview('bundle-completo'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPanel(array $attributes = []): PublicInfoPanel
    {
        return PublicInfoPanel::unguarded(fn (): PublicInfoPanel => PublicInfoPanel::create(array_merge([
            'slug' => 'panel-de-prueba',
            'tipo' => PublicInfoPanel::TIPO_HTML_BUNDLE,
            'titulo' => 'Panel de prueba',
            'descripcion' => 'Descripción de prueba',
            'publicado' => false,
            'orden' => 0,
        ], $attributes)));
    }

    private function storeAsset(string $path, string $contents): string
    {
        $relative = 'public-info-panels/'.$path;
        Storage::disk(config('voyager.storage.disk', 'private'))->put($relative, $contents);

        return $relative;
    }

    private function storePreview(string $slug): string
    {
        return $this->storeAsset(
            $slug.'/preview.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );
    }
}
