<?php

namespace Tests\Feature\Filament;

use App\Filament\Configuracion\Resources\PublicInfoPanels\Pages\ListPublicInfoPanels;
use App\Models\PublicInfoPanel;
use App\Models\Role;
use App\Models\User;
use App\Support\Dashboard\PublicInfoPanelInventory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PublicInfoPanelInventoryTest extends TestCase
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

    public function test_markdown_lists_every_panel_in_list_order_with_labels(): void
    {
        Carbon::setTestNow('2026-10-21 13:40:00');

        $this->createPanel([
            'slug' => 'z-panel',
            'titulo' => 'Panel Z',
            'tipo' => PublicInfoPanel::TIPO_EMBED_EXTERNO,
            'publicado' => true,
            'orden' => 2,
        ]);
        $this->createPanel([
            'slug' => 'a-panel',
            'titulo' => 'Panel A',
            'publicado' => false,
            'orden' => 1,
        ]);

        $markdown = PublicInfoPanelInventory::markdown(PublicInfoPanelInventory::panels());

        $this->assertStringContainsString('# Inventario de paneles públicos (Centinela)', $markdown);
        $this->assertStringContainsString('Generado: 2026-10-21 13:40 · Total: 2', $markdown);
        $this->assertStringContainsString('| Slug | Título | Tipo | Publicado |', $markdown);
        $this->assertStringContainsString('| a-panel | Panel A | HTML | No |', $markdown);
        $this->assertStringContainsString('| z-panel | Panel Z | Embed | Sí |', $markdown);
        $this->assertLessThan(strpos($markdown, 'z-panel'), strpos($markdown, 'a-panel'));
    }

    public function test_markdown_escapes_pipes_in_titles(): void
    {
        $this->createPanel([
            'slug' => 'cirugias',
            'titulo' => 'Cirugías | Programadas',
        ]);

        $markdown = PublicInfoPanelInventory::markdown(PublicInfoPanelInventory::panels());

        $this->assertStringContainsString('Cirugías \| Programadas', $markdown);
    }

    public function test_markdown_and_json_handle_empty_catalog(): void
    {
        $markdown = PublicInfoPanelInventory::markdown([]);
        $json = json_decode(PublicInfoPanelInventory::toJson([]), true);

        $this->assertStringContainsString('Total: 0', $markdown);
        $this->assertStringContainsString(PublicInfoPanelInventory::SIN_PANELES, $markdown);
        $this->assertSame(0, $json['total']);
        $this->assertSame([], $json['paneles']);
    }

    public function test_json_contract_uses_raw_type_and_boolean_published(): void
    {
        $this->createPanel([
            'slug' => 'panel-embed',
            'titulo' => 'Panel embed',
            'tipo' => PublicInfoPanel::TIPO_EMBED_EXTERNO,
            'publicado' => true,
        ]);

        $json = json_decode(PublicInfoPanelInventory::toJson(PublicInfoPanelInventory::panels()), true);

        $this->assertSame(['generado', 'total', 'paneles'], array_keys($json));
        $this->assertSame(1, $json['total']);
        $this->assertSame(
            ['slug' => 'panel-embed', 'titulo' => 'Panel embed', 'tipo' => 'embed_externo', 'publicado' => true],
            $json['paneles'][0],
        );
    }

    public function test_list_page_exposes_inventory_action_for_admin(): void
    {
        $this->createPanel(['slug' => 'panel-visible', 'titulo' => 'Panel visible']);

        $this->actingAs($this->admin)
            ->get(ListPublicInfoPanels::getUrl())
            ->assertOk()
            ->assertSee('Copiar inventario');
    }

    public function test_inventory_modal_shows_markdown_and_json_without_download(): void
    {
        $this->createPanel(['slug' => 'panel-uno', 'titulo' => 'Panel uno']);

        Livewire::actingAs($this->admin)
            ->test(ListPublicInfoPanels::class)
            ->mountAction('inventory')
            ->assertMountedActionModalSee('Inventario de paneles públicos')
            ->assertMountedActionModalSee('panel-uno')
            ->assertMountedActionModalSee('Markdown')
            ->assertMountedActionModalSee('JSON')
            ->assertMountedActionModalDontSee('Descargar');
    }

    public function test_non_admin_cannot_access_inventory(): void
    {
        $this->actingAs($this->referente)
            ->get(ListPublicInfoPanels::getUrl())
            ->assertForbidden();
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
}
