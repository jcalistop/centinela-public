<?php

namespace Tests\Feature\Configuracion\PublicInfoPanels;

use App\Filament\Configuracion\Resources\PublicInfoPanels\Pages\ListPublicInfoPanels;
use App\Livewire\Configuracion\PublicInfoPanels\PublicInfoPanelIndex;
use App\Models\PublicInfoPanel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesPublicInfoPanels;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use CreatesPublicInfoPanels;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPanelUsers();
    }

    public function test_admin_sees_listing_ordered_and_searchable(): void
    {
        $this->createPanel(['slug' => 'beta', 'titulo' => 'Beta', 'orden' => 2]);
        $this->createPanel(['slug' => 'alfa-uno', 'titulo' => 'Alfa', 'orden' => 1]);

        $this->actingAs($this->admin)
            ->get(route('centinela.admin.paneles-publicos'))
            ->assertOk()
            ->assertSeeLivewire(PublicInfoPanelIndex::class)
            ->assertSeeInOrder(['Alfa', 'Beta']);

        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelIndex::class)
            ->set('search', 'alfa-uno')
            ->assertSee('Alfa')
            ->assertDontSee('Beta')
            ->set('search', 'Beta')
            ->assertSee('Beta')
            ->assertDontSee('Alfa');
    }

    public function test_toggle_publishes_html_bundle_and_unpublishes(): void
    {
        $panel = $this->createBundlePanel();

        $component = Livewire::actingAs($this->admin)->test(PublicInfoPanelIndex::class);

        $component->call('togglePublicado', $panel->id)->assertDispatched('toast', type: 'success');
        $this->assertTrue($panel->fresh()->publicado);

        $component->call('togglePublicado', $panel->id)->assertDispatched('toast', type: 'success');
        $this->assertFalse($panel->fresh()->publicado);
    }

    public function test_toggle_publishes_allowlisted_embed(): void
    {
        $panel = $this->createPanel([
            'slug' => 'panel-embed',
            'tipo' => PublicInfoPanel::TIPO_EMBED_EXTERNO,
            'embed_url' => 'https://app.powerbi.com/view?r=eyDemo',
            'preview_png_path' => $this->storePreview('panel-embed'),
        ]);

        Livewire::actingAs($this->admin)->test(PublicInfoPanelIndex::class)->call('togglePublicado', $panel->id);

        $this->assertTrue($panel->fresh()->publicado);
    }

    public function test_toggle_unpublishing_is_always_allowed(): void
    {
        $panel = $this->createPanel(['slug' => 'publicado-sin-assets', 'publicado' => true]);

        Livewire::actingAs($this->admin)->test(PublicInfoPanelIndex::class)->call('togglePublicado', $panel->id);

        $this->assertFalse($panel->fresh()->publicado);
    }

    public function test_toggle_rejects_publish_without_preview_png(): void
    {
        $panel = $this->createBundlePanel('sin-preview', ['preview_png_path' => null]);

        $this->assertPublishRejected($panel);
    }

    public function test_toggle_rejects_bundle_without_csv(): void
    {
        $panel = $this->createBundlePanel('sin-csv', ['csv_path' => null]);

        $this->assertPublishRejected($panel);
    }

    public function test_toggle_rejects_embed_outside_allowlist(): void
    {
        $panel = $this->createPanel([
            'slug' => 'embed-prohibido',
            'tipo' => PublicInfoPanel::TIPO_EMBED_EXTERNO,
            'embed_url' => 'https://ejemplo-no-permitido.test/view',
            'preview_png_path' => $this->storePreview('embed-prohibido'),
        ]);

        $this->assertPublishRejected($panel);
    }

    public function test_toggle_rejects_html_with_scan_violations(): void
    {
        $html = '<html><head><script src="https://cdn.example.com/lib.js"></script></head><body>ok</body></html>';
        $panel = $this->createPanel([
            'slug' => 'html-inseguro',
            'html_path' => $this->storeAsset('html-inseguro/panel.html', $html),
            'csv_path' => $this->storeAsset('html-inseguro/datos.csv', "a,b\n1,2\n"),
            'preview_png_path' => $this->storePreview('html-inseguro'),
        ]);

        $this->assertPublishRejected($panel);
    }

    public function test_move_down_and_up_swap_with_neighbour(): void
    {
        $first = $this->createPanel(['slug' => 'a', 'titulo' => 'A', 'orden' => 0]);
        $second = $this->createPanel(['slug' => 'b', 'titulo' => 'B', 'orden' => 0]);

        $component = Livewire::actingAs($this->admin)->test(PublicInfoPanelIndex::class);

        $component->call('moveDown', $first->id);
        $this->assertSame(['b', 'a'], PublicInfoPanel::query()->orderBy('orden')->orderBy('titulo')->pluck('slug')->all());

        $component->call('moveUp', $first->id);
        $this->assertSame(['a', 'b'], PublicInfoPanel::query()->orderBy('orden')->orderBy('titulo')->pluck('slug')->all());
        $this->assertNotNull($second->fresh());
    }

    public function test_delete_selected_requires_confirmation_flow_and_removes_records(): void
    {
        $keep = $this->createPanel(['slug' => 'keep', 'titulo' => 'Keep']);
        $drop = $this->createPanel(['slug' => 'drop', 'titulo' => 'Drop']);

        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelIndex::class)
            ->set('selected', [(string) $drop->id])
            ->call('confirmDeleteSelected')
            ->assertSet('showDeleteConfirm', true)
            ->call('deleteSelected')
            ->assertSet('showDeleteConfirm', false)
            ->assertSet('selected', []);

        $this->assertNull(PublicInfoPanel::query()->find($drop->id));
        $this->assertNotNull(PublicInfoPanel::query()->find($keep->id));
    }

    public function test_inventory_modal_shows_markdown_and_json(): void
    {
        $this->createPanel(['slug' => 'inventariado', 'titulo' => 'Inventariado']);

        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelIndex::class)
            ->call('openInventory')
            ->assertSet('showInventory', true)
            ->assertSee('Copiar inventario')
            ->assertSee('inventariado')
            ->call('closeInventory')
            ->assertSet('showInventory', false);
    }

    public function test_share_modal_only_for_panels_with_required_assets(): void
    {
        $withAssets = $this->createBundlePanel('con-assets');
        $without = $this->createPanel(['slug' => 'sin-assets', 'titulo' => 'Sin assets']);

        $component = Livewire::actingAs($this->admin)->test(PublicInfoPanelIndex::class);

        $component->call('openShare', $without->id)
            ->assertSet('showShare', false)
            ->assertDispatched('toast', type: 'danger');

        $component->call('openShare', $withAssets->id)
            ->assertSet('showShare', true)
            ->assertSee('Compartir revisión del panel');
    }

    public function test_referente_and_parametrizador_get_403_on_http_and_component(): void
    {
        foreach ([$this->referente, $this->parametrizador] as $user) {
            $this->actingAs($user)->get(route('centinela.admin.paneles-publicos'))->assertForbidden();
            Livewire::actingAs($user)->test(PublicInfoPanelIndex::class)->assertForbidden();
        }
    }

    public function test_actions_called_directly_by_non_admin_are_forbidden(): void
    {
        $panel = $this->createBundlePanel();

        foreach ([$this->referente, $this->parametrizador] as $user) {
            $this->actingAs($user);

            foreach (
                [
                    'togglePublicado' => [$panel->id],
                    'moveUp' => [$panel->id],
                    'moveDown' => [$panel->id],
                    'deleteSelected' => [],
                    'openInventory' => [],
                    'openShare' => [$panel->id],
                    'openAgentGuide' => [],
                    'downloadAgentGuide' => [],
                ] as $action => $arguments
            ) {
                try {
                    (new PublicInfoPanelIndex)->{$action}(...$arguments);
                    $this->fail("La acción {$action} no debió ejecutarse para un no-admin.");
                } catch (HttpException $exception) {
                    $this->assertSame(403, $exception->getStatusCode(), $action);
                }
            }
        }

        $this->assertFalse($panel->fresh()->publicado);
        $this->assertNotNull(PublicInfoPanel::query()->find($panel->id));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('centinela.admin.paneles-publicos'))->assertRedirect();
    }

    public function test_admin_enters_voyager_with_filament_settings_gate_off(): void
    {
        config(['centinela.filament.enabled' => true, 'centinela.filament.settings_enabled' => false]);

        $this->actingAs($this->admin)
            ->get(route('centinela.admin.paneles-publicos'))
            ->assertOk()
            ->assertSeeLivewire(PublicInfoPanelIndex::class);
    }

    public function test_filament_surface_stays_reachable_dual_run(): void
    {
        config(['centinela.filament.enabled' => true, 'centinela.filament.settings_enabled' => true]);
        Filament::setCurrentPanel('configuracion');

        $this->actingAs($this->admin)
            ->get(ListPublicInfoPanels::getUrl())
            ->assertOk();
    }

    private function assertPublishRejected(PublicInfoPanel $panel): void
    {
        Livewire::actingAs($this->admin)
            ->test(PublicInfoPanelIndex::class)
            ->call('togglePublicado', $panel->id)
            ->assertDispatched('toast', type: 'danger');

        $this->assertFalse($panel->fresh()->publicado);
    }
}
