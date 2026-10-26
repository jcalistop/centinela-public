<?php

namespace Tests\Feature\Configuracion\PublicInfoPanels;

use App\Livewire\Configuracion\PublicInfoPanels\PublicInfoPanelEditor;
use App\Models\PublicInfoPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesPublicInfoPanels;
use Tests\TestCase;
use ZipArchive;

class EditorTest extends TestCase
{
    use CreatesPublicInfoPanels;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPanelUsers();
    }

    public function test_create_saves_and_redirects_to_show(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelEditor::class)
            ->set('titulo', 'Panel creado')
            ->assertSet('slug', 'panel-creado')
            ->set('descripcion', 'Descripción mínima para crear.')
            ->call('save')
            ->assertHasNoErrors();

        $panel = PublicInfoPanel::query()->where('slug', 'panel-creado')->first();
        $this->assertNotNull($panel);
        $this->assertFalse($panel->publicado);
        $component->assertRedirect(route('centinela.admin.paneles-publicos.show', ['panel' => $panel->id]));
    }

    public function test_create_with_uploads_and_publish_persists_paths(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelEditor::class)
            ->set('titulo', 'Con archivos')
            ->set('descripcion', 'Panel con archivos.')
            ->set('htmlUpload', UploadedFile::fake()->createWithContent('panel.html', '<html><body>ok</body></html>'))
            ->set('csvUpload', UploadedFile::fake()->createWithContent('datos.csv', "a,b\n1,2\n"))
            ->set('previewUpload', UploadedFile::fake()->createWithContent('preview.png', $this->pngBytes()))
            ->set('publicado', true)
            ->call('save')
            ->assertHasNoErrors();

        $panel = PublicInfoPanel::query()->where('slug', 'con-archivos')->firstOrFail();
        $this->assertTrue($panel->publicado);
        $this->assertSame('public-info-panels/con-archivos/panel.html', $panel->html_path);
        $this->assertSame('public-info-panels/con-archivos/datos.csv', $panel->csv_path);
        $this->assertSame('public-info-panels/con-archivos/preview.png', $panel->preview_png_path);
        $this->assertTrue(Storage::disk(config('voyager.storage.disk', 'private'))->exists($panel->html_path));
    }

    public function test_publish_without_assets_is_rejected_and_not_persisted(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelEditor::class)
            ->set('titulo', 'Sin assets')
            ->set('descripcion', 'Intenta publicar sin archivos.')
            ->set('publicado', true)
            ->call('save')
            ->assertHasErrors(['preview_png_path'])
            ->assertDispatched('toast', type: 'danger')
            ->assertNoRedirect();

        $this->assertNull(PublicInfoPanel::query()->where('slug', 'sin-assets')->first());
    }

    public function test_publish_embed_outside_allowlist_is_rejected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelEditor::class)
            ->set('titulo', 'Embed malo')
            ->set('descripcion', 'Embed fuera de allowlist.')
            ->set('tipo', PublicInfoPanel::TIPO_EMBED_EXTERNO)
            ->set('embed_url', 'https://ejemplo-no-permitido.test/view')
            ->call('save')
            ->assertHasErrors(['embed_url']);

        $this->assertNull(PublicInfoPanel::query()->where('slug', 'embed-malo')->first());
    }

    public function test_html_with_scan_violations_is_rejected_before_overwriting_assets(): void
    {
        $panel = $this->createBundlePanel('vivo', ['publicado' => true]);
        $original = Storage::disk(config('voyager.storage.disk', 'private'))->get($panel->html_path);

        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelEditor::class, ['panel' => $panel])
            ->set('htmlUpload', UploadedFile::fake()->createWithContent(
                'panel.html',
                '<html><head><script src="https://cdn.example.com/lib.js"></script></head><body>x</body></html>',
            ))
            ->call('save')
            ->assertHasErrors(['html_path'])
            ->assertDispatched('toast', type: 'danger');

        $this->assertSame($original, Storage::disk(config('voyager.storage.disk', 'private'))->get($panel->html_path));
    }

    public function test_slug_duplicate_is_rejected(): void
    {
        $this->createPanel(['slug' => 'ocupado']);

        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelEditor::class)
            ->set('titulo', 'Otro')
            ->set('slug', 'ocupado')
            ->set('descripcion', 'Slug duplicado.')
            ->call('save')
            ->assertHasErrors(['slug']);

        $this->assertSame(1, PublicInfoPanel::query()->count());
    }

    public function test_edit_freezes_slug_when_published_and_redirects_to_show(): void
    {
        $panel = $this->createBundlePanel('congelado', ['publicado' => true]);

        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelEditor::class, ['panel' => $panel])
            ->assertSet('slug', 'congelado')
            ->set('slug', 'otro-slug')
            ->set('titulo', 'Título nuevo')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('centinela.admin.paneles-publicos.show', ['panel' => $panel->id]));

        $fresh = $panel->fresh();
        $this->assertSame('congelado', $fresh->slug);
        $this->assertSame('Título nuevo', $fresh->titulo);
        $this->assertTrue($fresh->publicado);
    }

    public function test_valid_zip_preloads_fields_without_publishing(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelEditor::class)
            ->set('publicado', true)
            ->set('zip_precarga', $this->zipUpload([
                'manifest.json' => json_encode(['titulo' => 'Panel ZIP', 'descripcion' => 'Desde ZIP.', 'tipo' => 'html_bundle']),
                'panel.html' => '<html><body>ok</body></html>',
                'datos.csv' => "a,b\n1,2\n",
                'preview.png' => $this->pngBytes(),
            ]))
            ->assertSet('titulo', 'Panel ZIP')
            ->assertSet('slug', 'panel-zip')
            ->assertSet('publicado', false)
            ->assertSet('html_path', 'public-info-panels/panel-zip/panel.html')
            ->assertSet('zip_precarga', null)
            ->assertDispatched('toast', type: 'success');

        $this->assertNull(PublicInfoPanel::query()->where('slug', 'panel-zip')->first());
    }

    public function test_invalid_zip_does_not_mutate_form_and_clears_upload(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelEditor::class)
            ->set('titulo', 'Título previo')
            ->set('zip_precarga', $this->zipUpload([
                'manifest.json' => json_encode(['titulo' => 'Malo', 'descripcion' => 'Traversal.']),
                '../evil.html' => '<html></html>',
                'preview.png' => $this->pngBytes(),
            ]))
            ->assertSet('titulo', 'Título previo')
            ->assertSet('html_path', null)
            ->assertSet('zip_precarga', null)
            ->assertDispatched('toast', type: 'danger');
    }

    public function test_client_cannot_tamper_with_persisted_asset_paths(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelEditor::class)
            ->set('html_path', '../../../.env');
    }

    public function test_delete_panel_removes_record_and_redirects_to_index(): void
    {
        $panel = $this->createPanel(['slug' => 'borrable']);

        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelEditor::class, ['panel' => $panel])
            ->call('deletePanel')
            ->assertRedirect(route('centinela.admin.paneles-publicos'));

        $this->assertNull(PublicInfoPanel::query()->find($panel->id));
    }

    public function test_non_admin_gets_403_on_http_component_and_actions(): void
    {
        $panel = $this->createPanel();

        foreach ([$this->referente, $this->parametrizador] as $user) {
            $this->actingAs($user)->get(route('centinela.admin.paneles-publicos.create'))->assertForbidden();
            $this->actingAs($user)->get(route('centinela.admin.paneles-publicos.edit', ['panel' => $panel->id]))->assertForbidden();
            Livewire::actingAs($user)->test(PublicInfoPanelEditor::class)->assertForbidden();

            foreach (['save', 'preloadZip', 'deletePanel', 'openAgentGuide', 'downloadAgentGuide'] as $action) {
                try {
                    (new PublicInfoPanelEditor)->{$action}();
                    $this->fail("La acción {$action} no debió ejecutarse para un no-admin.");
                } catch (HttpException $exception) {
                    $this->assertSame(403, $exception->getStatusCode(), $action);
                }
            }
        }

        $this->assertNotNull(PublicInfoPanel::query()->find($panel->id));
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function zipUpload(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return UploadedFile::fake()->createWithContent('panel.zip', $bytes);
    }
}
