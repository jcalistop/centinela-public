<?php

namespace Tests\Feature\Filament;

use App\Filament\Configuracion\Resources\PublicInfoPanels\Pages\ViewPublicInfoPanel;
use App\Models\PublicInfoPanel;
use App\Models\Role;
use App\Models\User;
use App\Support\Dashboard\PublicInfoPanelPreview;
use App\Support\Dashboard\PublicInfoPanelShare;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicInfoPanelShareTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centinela.filament.enabled' => true,
            'centinela.filament.settings_enabled' => true,
        ]);
        Filament::setCurrentPanel('configuracion');

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
    }

    public function test_share_action_shows_public_url_for_published_panel(): void
    {
        $panel = $this->makePanel([
            'slug' => 'panel-publicado',
            'publicado' => true,
            'html_path' => 'public-info-panels/panel-publicado/panel.html',
            'csv_path' => 'public-info-panels/panel-publicado/datos.csv',
            'preview_png_path' => 'public-info-panels/panel-publicado/preview.png',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ViewPublicInfoPanel::class, ['record' => $panel->getRouteKey()])
            ->mountAction('sharePreview')
            ->assertMountedActionModalSee('Compartir panel publicado')
            ->assertMountedActionModalSee('URL pública')
            ->assertMountedActionModalSee(rawurlencode(route('centinela.publico.panel', ['slug' => 'panel-publicado'])));
    }

    public function test_share_action_keeps_signed_review_url_for_draft(): void
    {
        $panel = $this->makePanel([
            'slug' => 'panel-borrador',
            'publicado' => false,
            'html_path' => 'public-info-panels/panel-borrador/panel.html',
            'csv_path' => 'public-info-panels/panel-borrador/datos.csv',
            'preview_png_path' => 'public-info-panels/panel-borrador/preview.png',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ViewPublicInfoPanel::class, ['record' => $panel->getRouteKey()])
            ->mountAction('sharePreview')
            ->assertMountedActionModalSee('Compartir revisión del panel')
            ->assertMountedActionModalSee('URL de revisión (7 días)');
    }

    public function test_published_panel_shares_public_url_and_public_message(): void
    {
        $panel = $this->makePanel(['slug' => 'panel-publicado', 'publicado' => true]);

        $this->assertSame(route('centinela.publico.panel', ['slug' => 'panel-publicado']), PublicInfoPanelShare::url($panel));
        $this->assertSame('Panel público: Panel de prueba', PublicInfoPanelShare::subject($panel));

        $body = $this->mailtoBody($panel);

        $this->assertStringContainsString('está publicado en el dashboard público', $body);
        $this->assertStringNotContainsString('caduca', $body);
        $this->assertStringNotContainsString('aún no está publicado', $body);
        $this->assertStringNotContainsString('slug', $body);
    }

    public function test_unpublished_panel_shares_signed_preview_url_and_review_message(): void
    {
        $panel = $this->makePanel(['slug' => 'panel-borrador', 'publicado' => false]);

        $url = PublicInfoPanelShare::url($panel);

        $this->assertStringContainsString('/publico/paneles/panel-borrador/preview', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertSame('Revisión panel: Panel de prueba', PublicInfoPanelShare::subject($panel));

        $body = $this->mailtoBody($panel);

        $this->assertStringContainsString('caduca en aproximadamente', $body);
        $this->assertStringContainsString('aún no está publicado', $body);
        $this->assertStringNotContainsString('El panel está publicado en el dashboard público', $body);
        $this->assertStringNotContainsString('slug', $body);
    }

    public function test_preview_mailto_helper_keeps_review_copy_for_drafts(): void
    {
        $panel = $this->makePanel(['slug' => 'panel-borrador', 'publicado' => false]);

        $this->assertSame(PublicInfoPanelShare::mailtoHref($panel), PublicInfoPanelPreview::mailtoHref($panel));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePanel(array $attributes = []): PublicInfoPanel
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

    private function mailtoBody(PublicInfoPanel $panel): string
    {
        $href = PublicInfoPanelShare::mailtoHref($panel);
        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);

        return is_string($query['body'] ?? null) ? $query['body'] : '';
    }
}
