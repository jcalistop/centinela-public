<?php

namespace Tests\Feature\Filament;

use App\Filament\Configuracion\Resources\PublicInfoPanels\Pages\CreatePublicInfoPanel;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Pages\EditPublicInfoPanel;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Pages\ViewPublicInfoPanel;
use App\Models\PublicInfoPanel;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PublicInfoPanelResourceTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_view_panel(): void
    {
        $panel = $this->createUnpublishedPanel();

        $this->actingAs($this->admin)
            ->get(ViewPublicInfoPanel::getUrl(['record' => $panel]))
            ->assertOk()
            ->assertSee('Panel de prueba')
            ->assertSee('Vista previa', false)
            ->assertSee('Aún no hay HTML ni embed para previsualizar', false);

        Livewire::actingAs($this->admin)
            ->test(ViewPublicInfoPanel::class, ['record' => $panel->getRouteKey()])
            ->assertOk()
            ->assertSee('Panel de prueba')
            ->assertSee('fi-public-info-panel-preview', false);
    }

    public function test_view_shows_html_iframe_when_assets_exist(): void
    {
        Carbon::setTestNow('2026-10-25 20:00:00');

        $panel = $this->createUnpublishedPanelWithAssets();
        $frameSrc = $panel->frameSrc();

        $this->actingAs($this->admin)
            ->get(ViewPublicInfoPanel::getUrl(['record' => $panel]))
            ->assertOk()
            ->assertSee('fi-public-info-panel-preview-frame', false)
            ->assertSee('sandbox="allow-scripts"', false)
            ->assertSee($frameSrc, false)
            ->assertDontSee('allow-same-origin', false);
    }

    public function test_view_shows_embed_iframe(): void
    {
        $panel = $this->createUnpublishedEmbedPanel('https://app.powerbi.com/view?r=eyDemo');

        $this->actingAs($this->admin)
            ->get(ViewPublicInfoPanel::getUrl(['record' => $panel]))
            ->assertOk()
            ->assertSee('fi-public-info-panel-preview-frame', false)
            ->assertSee('https://app.powerbi.com/view?r=eyDemo', false)
            ->assertSee('allow-same-origin', false);
    }

    public function test_create_hides_create_another_and_redirects_to_view(): void
    {
        $livewire = Livewire::actingAs($this->admin)
            ->test(CreatePublicInfoPanel::class)
            ->assertOk();

        $this->assertFalse($livewire->instance()->canCreateAnother());

        $livewire
            ->fillForm([
                'tipo' => PublicInfoPanel::TIPO_HTML_BUNDLE,
                'titulo' => 'Panel creado',
                'slug' => 'panel-creado',
                'descripcion' => 'Descripción mínima para crear.',
                'publicado' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $panel = PublicInfoPanel::query()->where('slug', 'panel-creado')->first();
        $this->assertNotNull($panel);
        $livewire->assertRedirect(ViewPublicInfoPanel::getUrl(['record' => $panel]));
    }

    public function test_edit_redirects_to_view_after_save(): void
    {
        $panel = $this->createUnpublishedPanel();

        Livewire::actingAs($this->admin)
            ->test(EditPublicInfoPanel::class, ['record' => $panel->getRouteKey()])
            ->assertOk()
            ->assertSee('Resumen')
            ->assertSee('Autoría')
            ->assertSee('Origen')
            ->assertSee('Vista previa')
            ->fillForm([
                'titulo' => 'Panel actualizado',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(ViewPublicInfoPanel::getUrl(['record' => $panel]));

        $this->assertSame('Panel actualizado', $panel->fresh()->titulo);
    }

    public function test_non_admin_cannot_view_panel(): void
    {
        $panel = $this->createUnpublishedPanel();

        $this->actingAs($this->referente)
            ->get(ViewPublicInfoPanel::getUrl(['record' => $panel]))
            ->assertForbidden();
    }

    public function test_admin_cannot_view_when_settings_flag_is_off(): void
    {
        config(['centinela.filament.settings_enabled' => false]);

        $panel = $this->createUnpublishedPanel();

        $this->actingAs($this->admin)
            ->get(ViewPublicInfoPanel::getUrl(['record' => $panel]))
            ->assertForbidden();
    }

    private function createUnpublishedPanel(): PublicInfoPanel
    {
        return PublicInfoPanel::unguarded(fn () => PublicInfoPanel::create([
            'slug' => 'panel-de-prueba',
            'tipo' => PublicInfoPanel::TIPO_HTML_BUNDLE,
            'titulo' => 'Panel de prueba',
            'descripcion' => 'Descripción de prueba',
            'publicado' => false,
            'orden' => 0,
        ]));
    }

    private function createUnpublishedPanelWithAssets(): PublicInfoPanel
    {
        $disk = Storage::disk(config('voyager.storage.disk', 'private'));
        $html = 'public-info-panels/html/panel-con-assets.html';
        $csv = 'public-info-panels/csv/panel-con-assets.csv';
        $png = 'public-info-panels/preview/panel-con-assets.png';

        $disk->put($html, '<html><body><h1>Panel</h1></body></html>');
        $disk->put($csv, "a,b\n1,2\n");
        $disk->put($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        return PublicInfoPanel::unguarded(fn () => PublicInfoPanel::create([
            'slug' => 'panel-con-assets',
            'tipo' => PublicInfoPanel::TIPO_HTML_BUNDLE,
            'titulo' => 'Panel con assets',
            'descripcion' => 'Descripción con archivos',
            'html_path' => $html,
            'csv_path' => $csv,
            'preview_png_path' => $png,
            'publicado' => false,
            'orden' => 0,
        ]));
    }

    private function createUnpublishedEmbedPanel(string $embedUrl): PublicInfoPanel
    {
        return PublicInfoPanel::unguarded(fn () => PublicInfoPanel::create([
            'slug' => 'panel-embed',
            'tipo' => PublicInfoPanel::TIPO_EMBED_EXTERNO,
            'titulo' => 'Panel embed',
            'descripcion' => 'Descripción embed',
            'embed_url' => $embedUrl,
            'publicado' => false,
            'orden' => 0,
        ]));
    }
}
