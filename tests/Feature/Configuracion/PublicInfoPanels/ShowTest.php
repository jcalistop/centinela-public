<?php

namespace Tests\Feature\Configuracion\PublicInfoPanels;

use App\Livewire\Configuracion\PublicInfoPanels\PublicInfoPanelShow;
use App\Models\PublicInfoPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesPublicInfoPanels;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use CreatesPublicInfoPanels;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPanelUsers();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_bundle_iframe_is_sandboxed_without_same_origin(): void
    {
        Carbon::setTestNow('2026-10-25 20:00:00');

        $panel = $this->createBundlePanel();
        $frameSrc = $panel->frameSrc();

        $this->actingAs($this->admin)
            ->get(route('centinela.admin.paneles-publicos.show', ['panel' => $panel->id]))
            ->assertOk()
            ->assertSeeLivewire(PublicInfoPanelShow::class)
            ->assertSee('public-info-panel-preview-frame', false)
            ->assertSee('sandbox="allow-scripts"', false)
            ->assertSee($frameSrc, false)
            ->assertDontSee('allow-same-origin', false);
    }

    public function test_embed_iframe_uses_embed_sandbox(): void
    {
        $panel = $this->createPanel([
            'slug' => 'panel-embed',
            'tipo' => PublicInfoPanel::TIPO_EMBED_EXTERNO,
            'embed_url' => 'https://app.powerbi.com/view?r=eyDemo',
            'preview_png_path' => $this->storePreview('panel-embed'),
        ]);

        $this->actingAs($this->admin)
            ->get(route('centinela.admin.paneles-publicos.show', ['panel' => $panel->id]))
            ->assertOk()
            ->assertSee('https://app.powerbi.com/view?r=eyDemo', false)
            ->assertSee('allow-same-origin', false);
    }

    public function test_without_assets_shows_empty_preview_message(): void
    {
        $panel = $this->createPanel();

        $this->actingAs($this->admin)
            ->get(route('centinela.admin.paneles-publicos.show', ['panel' => $panel->id]))
            ->assertOk()
            ->assertSee('Aún no hay HTML ni embed para previsualizar', false);
    }

    public function test_share_unpublished_uses_signed_review_url(): void
    {
        $panel = $this->createBundlePanel();

        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelShow::class, ['panel' => $panel])
            ->call('openShare', $panel->id)
            ->assertSet('showShare', true)
            ->assertSee('Compartir revisión del panel')
            ->assertSee('URL de revisión')
            ->assertSee('signature=', false);
    }

    public function test_share_published_uses_public_url(): void
    {
        $panel = $this->createBundlePanel('publicado', ['publicado' => true]);

        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelShow::class, ['panel' => $panel])
            ->call('openShare', $panel->id)
            ->assertSee('Compartir panel publicado')
            ->assertSee(route('centinela.publico.panel', ['slug' => 'publicado']), false);
    }

    public function test_agent_guide_modal_and_download(): void
    {
        $panel = $this->createPanel();

        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelShow::class, ['panel' => $panel])
            ->call('openAgentGuide')
            ->assertSet('showAgentGuide', true)
            ->assertSee('Contratos para agentes')
            ->call('downloadAgentGuide')
            ->assertFileDownloaded('contrato-agentes-paneles.md');
    }

    public function test_panel_id_cannot_be_tampered_by_client(): void
    {
        $panel = $this->createPanel();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelShow::class, ['panel' => $panel])
            ->set('panelId', 999);
    }

    public function test_non_admin_gets_403_on_http_and_component(): void
    {
        $panel = $this->createPanel();

        foreach ([$this->referente, $this->parametrizador] as $user) {
            $this->actingAs($user)
                ->get(route('centinela.admin.paneles-publicos.show', ['panel' => $panel->id]))
                ->assertForbidden();

            Livewire::actingAs($user)->test(PublicInfoPanelShow::class, ['panel' => $panel])->assertForbidden();

            try {
                (new PublicInfoPanelShow)->downloadAgentGuide();
                $this->fail('downloadAgentGuide no debió ejecutarse para un no-admin.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }
}
