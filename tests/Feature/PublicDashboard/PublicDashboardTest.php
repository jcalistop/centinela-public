<?php

namespace Tests\Feature\PublicDashboard;

use App\Enums\IndicatorType\EtapaVigente;
use App\Filament\Configuracion\Resources\PublicInfoPanels\Schemas\PublicInfoPanelForm;
use App\Models\CenHieDependency;
use App\Models\IndicatorType;
use App\Models\IndicatorTypeSetting;
use App\Models\PublicInfoPanel;
use App\Models\ServiceModuleToggle;
use App\Models\User;
use App\Models\Year;
use App\Services\Dashboard\PublicSsDashboardService;
use App\Support\Configuration\ConfigurationService;
use App\Support\Configuration\PublicDashboardGate;
use App\Support\Configuration\PublicSsDashboardGate;
use App\Support\Dashboard\PublicInfoPanelAgentGuide;
use App\Support\Dashboard\PublicInfoPanelEmbedUrl;
use App\Support\Dashboard\PublicInfoPanelHtmlScanner;
use App\Support\Dashboard\PublicInfoPanelPreview;
use App\Support\Dashboard\PublicInfoPanelZipPreloader;
use App\Support\Dashboard\PublicPanelInconsistencyMailto;
use App\Support\Dashboard\PublicPanelSubmissionMailto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use TCG\Voyager\Models\Role;
use Tests\TestCase;

class PublicDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Year $year;

    private CenHieDependency $enabledSs;

    private CenHieDependency $disabledSs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enablePublicDashboard(ss: true);

        $this->year = Year::unguarded(fn () => Year::create([
            'ano' => 2026,
            'default' => true,
        ]));

        Year::unguarded(fn () => Year::create([
            'ano' => 2025,
            'default' => false,
        ]));

        $this->enabledSs = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => 'SS01',
            'nombre' => 'Servicio de Salud Habilitado',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));

        $this->disabledSs = CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'codigo' => 'SS99',
            'nombre' => 'Servicio de Salud Deshabilitado',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => false,
        ]));
    }

    public function test_landing_redirects_to_login_when_public_dashboard_off(): void
    {
        $this->enablePublicDashboard(dash: false, ss: false);

        $this->get(route('centinela.publico.landing'))
            ->assertRedirect(route('voyager.login'));
        $this->get('/')
            ->assertRedirect(route('voyager.login'));
        $this->get(route('centinela.publico.plataforma'))
            ->assertRedirect(route('voyager.login'));
        $this->get(route('centinela.publico.ss', ['ss' => $this->enabledSs->id]))
            ->assertRedirect(route('voyager.login'));
    }

    public function test_plataforma_page_shows_institutional_copy_without_ss_or_panels(): void
    {
        $panel = $this->createPublishedPanel('panel-plataforma');

        $response = $this->get(route('centinela.publico.plataforma'));

        $response->assertOk();
        $response->assertSee('¿Qué es Centinela?', false);
        $response->assertSee('Para quién es', false);
        $response->assertSee('Objetivos', false);
        $response->assertSee('plataforma web de gestión', false);
        $response->assertSee('dashboard público', false);
        $response->assertSee('no reemplaza', false);
        $response->assertSee('cuenta e inicio de sesión', false);
        $response->assertSee('Módulos de la plataforma', false);
        $response->assertSee('Habilitados / en uso operativo', false);
        $response->assertSee('COMGES', false);
        $response->assertSee('Metas Ley 18.834', false);
        $response->assertSee('En desarrollo', false);
        $response->assertSee('ADP', false);
        $response->assertSee('Servicio de Salud de Reloncaví', false);
        $response->assertSee('Volver al dashboard público', false);
        $response->assertSee(route('centinela.publico.landing'), false);
        $response->assertSee('solicitar acceso', false);
        $response->assertSee(route('centinela.access-request.create'), false);
        $response->assertDontSee('vision-funcional.svg', false);
        $response->assertDontSee('/admin/soporte', false);
        $response->assertDontSee($panel->titulo, false);
        $response->assertDontSee('Servicio de Salud Habilitado', false);
        $response->assertDontSee('Paneles de información nacional', false);
    }

    public function test_landing_header_links_to_plataforma_before_access_request(): void
    {
        $response = $this->get(route('centinela.publico.landing'));

        $response->assertOk();
        $response->assertSee('¿Qué es Centinela?', false);
        $content = $response->getContent();
        $plataformaPos = strpos($content, route('centinela.publico.plataforma'));
        $accessPos = strpos($content, route('centinela.access-request.create'));
        $this->assertNotFalse($plataformaPos);
        $this->assertNotFalse($accessPos);
        $this->assertLessThan($accessPos, $plataformaPos);
    }

    public function test_panel_routes_redirect_to_login_when_public_dashboard_off(): void
    {
        $panel = $this->createPublishedPanel('panel-off');
        $this->enablePublicDashboard(dash: false, ss: false);

        $this->get(route('centinela.publico.panel', ['slug' => $panel->slug]))
            ->assertRedirect(route('voyager.login'));
        $this->get(route('centinela.storage.public-panel', ['slug' => $panel->slug, 'asset' => 'html']))
            ->assertRedirect(route('voyager.login'));
    }

    public function test_landing_shows_panels_without_ss_when_ss_flag_off(): void
    {
        $this->enablePublicDashboard(dash: true, ss: false);
        $panel = $this->createPublishedPanel('panel-nacional');

        $response = $this->get(route('centinela.publico.landing'));

        $response->assertOk();
        $response->assertSee('Paneles de información nacional', false);
        $response->assertSee($panel->titulo, false);
        $response->assertDontSee('>Servicios de Salud</h2>', false);
        $response->assertDontSee('Servicio de Salud Habilitado', false);
        $this->get(route('centinela.publico.ss', ['ss' => $this->enabledSs->id]))->assertNotFound();
    }

    public function test_landing_lists_only_enabled_ss_without_compliance_percent(): void
    {
        $response = $this->get(route('centinela.publico.landing'));

        $response->assertOk();
        $response->assertSee('Servicio de Salud Habilitado', false);
        $response->assertDontSee('Servicio de Salud Deshabilitado', false);
        $response->assertSee('Iniciar sesión', false);
        $response->assertDontSee('Cumplimiento', false);
        $response->assertDontSee('number_format', false);
        $response->assertDontSee('class="semaforo', false);
        $content = $response->getContent();
        $this->assertDoesNotMatchRegularExpression('/\d+[.,]\d+\s*%/', $content);
        $this->assertDoesNotMatchRegularExpression('/>\s*\d+\s*%\s*</', $content);
        $response->assertSee(asset(CenHieDependency::DEFAULT_LOGO_ASSET), false);
    }

    public function test_root_serves_public_landing(): void
    {
        $this->get('/')->assertOk()->assertSee('Paneles de información nacional', false);
        $this->get('/')->assertSee('Servicios de Salud', false);
    }

    public function test_landing_shows_publish_panel_invite_with_mailto_when_configured(): void
    {
        Config::set('centinela.contact_mail_to', 'paneles@example.gob.cl');

        $response = $this->get(route('centinela.publico.landing'));

        $response->assertOk();
        $response->assertSee('¿Quieres publicar un panel?', false);
        $response->assertSee('cen-invite-panel-dialog', false);
        $response->assertSee('Cómo enviar tu propuesta', false);
        $response->assertSee('Administrador de la plataforma', false);
        $response->assertSee('Actualización periódica', false);
        $response->assertSee('Protección de datos', false);
        $response->assertSee('¿Trabajas con agentes de IA?', false);
        $response->assertSee(route('centinela.publico.contrato-agentes'), false);
        $response->assertSee('paneles@example.gob.cl', false);
        $response->assertSee('mailto:paneles@example.gob.cl?subject=', false);
        $response->assertSee(e(PublicPanelSubmissionMailto::href()), false);
        $response->assertDontSee('Enviar solicitud por correo', false);
        $response->assertDontSee('Contacto no disponible', false);
    }

    public function test_landing_degrades_publish_cta_when_mail_to_empty(): void
    {
        Config::set('centinela.contact_mail_to', '');

        $response = $this->get(route('centinela.publico.landing'));

        $response->assertOk();
        $response->assertSee('¿Quieres publicar un panel?', false);
        $response->assertSee('Administrador de la plataforma', false);
        $response->assertSee('Contacto no disponible', false);
        $response->assertDontSee('mailto:', false);
        $response->assertDontSee('Enviar solicitud por correo', false);
    }

    public function test_plataforma_does_not_show_publish_panel_invite(): void
    {
        Config::set('centinela.contact_mail_to', 'paneles@example.gob.cl');

        $this->get(route('centinela.publico.plataforma'))
            ->assertOk()
            ->assertDontSee('¿Quieres publicar un panel?', false)
            ->assertDontSee('cen-invite-panel-dialog', false);
    }

    public function test_published_panel_shows_inconsistency_mailto_when_configured(): void
    {
        Config::set('centinela.contact_mail_to', 'paneles@example.gob.cl');
        $panel = $this->createPublishedPanel('inconsistencia-ok', 'Panel con datos');

        $response = $this->get(route('centinela.publico.panel', ['slug' => $panel->slug]));

        $response->assertOk();
        $response->assertSee('Reportar inconsistencia', false);
        $response->assertSee('mailto:paneles@example.gob.cl?subject=', false);
        $response->assertSee(e(PublicPanelInconsistencyMailto::href($panel)), false);
        $this->assertStringContainsString(
            rawurlencode('Inconsistencia panel: Panel con datos'),
            (string) PublicPanelInconsistencyMailto::href($panel)
        );
        $this->assertStringContainsString(
            rawurlencode('Slug: inconsistencia-ok'),
            (string) PublicPanelInconsistencyMailto::href($panel)
        );
    }

    public function test_published_panel_hides_inconsistency_cta_when_mail_to_empty(): void
    {
        Config::set('centinela.contact_mail_to', '');
        $panel = $this->createPublishedPanel('inconsistencia-sin-mail');

        $this->get(route('centinela.publico.panel', ['slug' => $panel->slug]))
            ->assertOk()
            ->assertDontSee('Reportar inconsistencia', false)
            ->assertDontSee('mailto:', false);
    }

    public function test_preview_does_not_show_inconsistency_mailto(): void
    {
        Config::set('centinela.contact_mail_to', 'paneles@example.gob.cl');
        $panel = $this->createPublishedPanel('inconsistencia-preview', 'Preview sin reporte');
        $panel->publicado = false;
        $panel->save();

        $this->get(PublicInfoPanelPreview::signedUrl($panel))
            ->assertOk()
            ->assertSee('revisión', false)
            ->assertDontSee('Reportar inconsistencia', false);
    }

    public function test_show_panel_uses_seamless_iframe_without_preview(): void
    {
        $panel = $this->createPublishedPanel('comges-nacional');

        $response = $this->get(route('centinela.publico.panel', ['slug' => $panel->slug]));

        $response->assertOk();
        $response->assertSee($panel->titulo, false);
        $response->assertSee('cen-publico-panel-frame', false);
        $response->assertSee('sandbox="allow-scripts"', false);
        $response->assertSee('allowfullscreen', false);
        $response->assertSee('Pantalla completa', false);
        $response->assertSee($panel->htmlEmbedUrl(), false);
        $response->assertSee(route('centinela.storage.public-panel', ['slug' => $panel->slug, 'asset' => 'csv']), false);
        $response->assertDontSee('cen-publico-panel-embed', false);
        $response->assertDontSee($panel->previewUrl(), false);
        $this->get(route('centinela.storage.public-panel', ['slug' => $panel->slug, 'asset' => 'html']))->assertOk();
        $this->get(route('centinela.storage.public-panel', ['slug' => $panel->slug, 'asset' => 'csv']))->assertOk();
        $this->get(route('centinela.storage.public-panel', ['slug' => $panel->slug, 'asset' => 'preview']))->assertOk();
    }

    public function test_embed_panel_shows_external_iframe_without_csv_download(): void
    {
        $panel = $this->createPublishedEmbedPanel(
            'powerbi-nacional',
            'https://app.powerbi.com/view?r=eyDemo',
            'Panel Power BI',
            [
                'autor' => 'Ana Pérez',
                'institucion' => 'SS Demo',
                'version_panel' => '1.0',
            ]
        );

        $response = $this->get(route('centinela.publico.panel', ['slug' => $panel->slug]));

        $response->assertOk();
        $response->assertSee('Panel Power BI', false);
        $response->assertSee('https://app.powerbi.com/view?r=eyDemo', false);
        $response->assertSee('allow-same-origin', false);
        $response->assertSee('versión 1.0', false);
        $response->assertDontSee('Ana Pérez', false);
        $response->assertDontSee('SS Demo', false);
        $response->assertDontSee('Descargar CSV', false);
        $response->assertSee($panel->titulo, false);
        $this->get(route('centinela.publico.landing'))->assertSee('Panel Power BI', false);
    }

    public function test_embed_url_allowlist_rejects_unknown_hosts(): void
    {
        $this->assertTrue(PublicInfoPanelEmbedUrl::isAllowed('https://app.powerbi.com/view?r=x'));
        $this->assertTrue(PublicInfoPanelEmbedUrl::isAllowed('https://public.tableau.com/views/demo'));
        $this->assertTrue(PublicInfoPanelEmbedUrl::isAllowed('https://lookerstudio.google.com/embed/reporting/x'));
        $this->assertFalse(PublicInfoPanelEmbedUrl::isAllowed('http://app.powerbi.com/view?r=x'));
        $this->assertFalse(PublicInfoPanelEmbedUrl::isAllowed('https://evil.example/embed'));
        $this->assertFalse(PublicInfoPanelEmbedUrl::isAllowed('https://www.arcgis.com/home'));
        $this->assertTrue(PublicInfoPanelEmbedUrl::isAllowed('https://www.arcgis.com/apps/dashboards/abc'));
    }

    public function test_assert_publishable_embed_requires_url_and_preview(): void
    {
        $this->expectException(ValidationException::class);
        PublicInfoPanelForm::assertPublishable([
            'tipo' => PublicInfoPanel::TIPO_EMBED_EXTERNO,
            'titulo' => 'X',
            'descripcion' => 'Y',
            'publicado' => true,
            'embed_url' => 'https://evil.example/x',
            'preview_png_path' => 'public-info-panels/preview/x.png',
        ]);
    }

    public function test_csv_download_prefixes_utf8_bom_for_excel_accents(): void
    {
        $panel = $this->createPublishedPanel('csv-acentos');
        $disk = Storage::disk(config('voyager.storage.disk', 'private'));
        $disk->put($panel->csv_path, "nombre,region\nÑuble,Región\n");

        $response = $this->get(route('centinela.storage.public-panel', [
            'slug' => $panel->slug,
            'asset' => 'csv',
        ]));

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('Ñuble', $body);
        $this->assertStringContainsString('Región', $body);
        // Disco sin mutar (sin BOM persistido).
        $this->assertStringStartsWith('nombre', (string) $disk->get($panel->csv_path));
    }

    public function test_html_response_injects_base_csp_cors_and_relative_csv_resolves(): void
    {
        $panel = $this->createPublishedPanel('vinculo-html-csv');

        $html = $this->get(route('centinela.storage.public-panel', [
            'slug' => $panel->slug,
            'asset' => 'html',
        ]));

        $html->assertOk();
        $html->assertSee('<base href="', false);
        $html->assertSee('/storage/public-info-panels/vinculo-html-csv/r/', false);
        $html->assertHeader('Access-Control-Allow-Origin', '*');
        $csp = $html->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);

        $relative = $this->get(route('centinela.storage.public-panel-relative', [
            'slug' => $panel->slug,
            'path' => 'datos-cualquiera.csv',
        ]));

        $relative->assertOk();
        $relative->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $relative->assertHeader('Access-Control-Allow-Origin', '*');
        $fileResponse = $relative->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $fileResponse);
        $this->assertStringContainsString('a,b', file_get_contents($fileResponse->getFile()->getPathname()));
    }

    public function test_html_scanner_rejects_dangerous_patterns_and_allows_clean_inline(): void
    {
        $scanner = app(PublicInfoPanelHtmlScanner::class);

        $this->assertSame([], $scanner->violations(
            '<html><body><script>fetch("./datos.csv")</script></body></html>'
        ));
        $this->assertNotEmpty($scanner->violations(
            '<script src="https://evil.example/x.js"></script>'
        ));
        $this->assertNotEmpty($scanner->violations(
            '<a href="javascript:alert(1)">x</a>'
        ));

        $disk = Storage::disk(config('voyager.storage.disk', 'private'));
        $badPath = 'public-info-panels/scan/bad.html';
        $disk->put($badPath, '<script src="https://evil.example/x.js"></script>');

        $this->expectException(ValidationException::class);
        PublicInfoPanelForm::assertHtmlClean(['html_path' => $badPath]);
    }

    public function test_agent_guide_markdown_is_downloadable_for_authenticated_users(): void
    {
        $this->assertFileExists(PublicInfoPanelAgentGuide::absolutePath());
        $contents = PublicInfoPanelAgentGuide::contents();
        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('Qué es este documento', $contents);
        $this->assertStringContainsString('Contrato ZIP', $contents);
        $this->assertStringContainsString('preview.png', $contents);
        $this->assertStringContainsString('José Calisto', $contents);
        $this->assertStringContainsString('Control de versión', $contents);
        $this->assertStringContainsString('**Versión**', $contents);
        $this->assertStringContainsString('1.3', $contents);
        $this->assertStringContainsString('2026-10-17', $contents);
        $this->assertStringContainsString('Departamento de Control de Gestión', $contents);
        $this->assertStringContainsString('Servicio de Salud del Reloncaví', $contents);
        $this->assertStringNotContainsString('Contacto institucional', $contents);
        $this->assertStringNotContainsString('{{CONTACT_MAIL_TO}}', $contents);
        $modalHtml = view('filament.public-info-panels.agent-guide-modal')->render();
        $this->assertStringContainsString('Copiar', $modalHtml);
        $this->assertStringContainsString('Descargar .md', $modalHtml);
        $this->assertStringContainsString(route('filament.public-info-panels.agent-guide'), $modalHtml);
        $this->assertStringContainsString('Contrato ZIP', $modalHtml);

        $this->get(route('filament.public-info-panels.agent-guide'))
            ->assertRedirect();

        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrador']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->assertInstanceOf(User::class, $user);

        $response = $this->actingAs($user)->get(route('filament.public-info-panels.agent-guide'));
        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString(
            'contrato-agentes-paneles.md',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_guest_can_download_agent_contract_when_dashboard_on(): void
    {
        $response = $this->get(route('centinela.publico.contrato-agentes'));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString(
            'contrato-agentes-paneles.md',
            (string) $response->headers->get('content-disposition')
        );
        $response->assertDontSee('Contacto institucional', false);
    }

    public function test_guest_contract_download_redirects_when_dashboard_off(): void
    {
        $this->enablePublicDashboard(dash: false, ss: true);

        $this->get(route('centinela.publico.contrato-agentes'))
            ->assertRedirect(route('voyager.login'));
    }

    public function test_zip_preloader_accepts_html_bundle_contract(): void
    {
        $zipPath = $this->makeTempZip([
            'manifest.json' => json_encode([
                'titulo' => 'Panel ZIP Bundle',
                'descripcion' => 'Descripción de prueba ZIP',
                'tipo' => PublicInfoPanel::TIPO_HTML_BUNDLE,
                'autor' => 'Equipo Demo',
            ], JSON_THROW_ON_ERROR),
            'panel.html' => '<html><body>ok</body></html>',
            'datos.csv' => "a,b\n1,2\n",
            'preview.png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        ]);

        $state = app(PublicInfoPanelZipPreloader::class)->preloadFromPath($zipPath);

        $this->assertSame('Panel ZIP Bundle', $state['titulo']);
        $this->assertSame(PublicInfoPanel::TIPO_HTML_BUNDLE, $state['tipo']);
        $this->assertFalse($state['publicado']);
        $this->assertSame('Equipo Demo', $state['autor']);
        $this->assertNotEmpty($state['html_path']);
        $this->assertNotEmpty($state['csv_path']);
        $this->assertNotEmpty($state['preview_png_path']);
        $this->assertSame('panel-zip-bundle', $state['slug']);
        $this->assertStringStartsWith('public-info-panels/panel-zip-bundle/', $state['html_path']);
        $this->assertStringStartsWith('public-info-panels/panel-zip-bundle/', $state['csv_path']);
        $this->assertStringStartsWith('public-info-panels/panel-zip-bundle/', $state['preview_png_path']);
        $this->assertTrue(Storage::disk(config('voyager.storage.disk', 'private'))->exists($state['html_path']));
        $this->assertTrue(PublicInfoPanelForm::isTrustedPreloadedFilePath($state['html_path']));
        $this->assertTrue(PublicInfoPanelForm::isTrustedPreloadedFilePath($state['csv_path']));
        $this->assertTrue(PublicInfoPanelForm::isTrustedPreloadedFilePath($state['preview_png_path']));
    }

    public function test_trusted_preloaded_path_rejects_traversal_and_foreign_files(): void
    {
        $this->assertFalse(PublicInfoPanelForm::isTrustedPreloadedFilePath('public-info-panels/../etc/passwd'));
        $this->assertFalse(PublicInfoPanelForm::isTrustedPreloadedFilePath('public-info-panels/foo/bar.html'));
        $this->assertFalse(PublicInfoPanelForm::isTrustedPreloadedFilePath('C:\\Users\\jcali\\OneDrive\\panel.html'));
        $this->assertFalse(PublicInfoPanelForm::isTrustedPreloadedFilePath('public-info-panels/demo/panel.html'));

        $disk = Storage::disk(config('voyager.storage.disk', 'private'));
        $legacy = 'public-info-panels/zip-preload/34a1975e-312a-49b2-af6e-d6b2fb5044d9/panel.html';
        $disk->put($legacy, '<html></html>');
        $this->assertTrue(PublicInfoPanelForm::isTrustedPreloadedFilePath($legacy));
    }

    public function test_zip_preloader_rejects_path_traversal_and_bad_embed(): void
    {
        $traversal = $this->makeTempZip([
            '../evil.html' => '<html></html>',
            'manifest.json' => json_encode([
                'titulo' => 'X',
                'descripcion' => 'Y',
                'tipo' => PublicInfoPanel::TIPO_HTML_BUNDLE,
            ], JSON_THROW_ON_ERROR),
            'panel.html' => '<html></html>',
            'datos.csv' => "a\n1\n",
            'preview.png' => 'png',
        ]);

        try {
            app(PublicInfoPanelZipPreloader::class)->preloadFromPath($traversal);
            $this->fail('Se esperaba ValidationException por path traversal.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $badEmbed = $this->makeTempZip([
            'manifest.json' => json_encode([
                'titulo' => 'Embed malo',
                'descripcion' => 'Desc',
                'tipo' => PublicInfoPanel::TIPO_EMBED_EXTERNO,
                'embed_url' => 'https://evil.example/embed',
            ], JSON_THROW_ON_ERROR),
            'preview.png' => 'png',
        ]);

        $this->expectException(ValidationException::class);
        app(PublicInfoPanelZipPreloader::class)->preloadFromPath($badEmbed);
    }

    public function test_unpublished_or_unknown_panel_returns_404(): void
    {
        $panel = $this->createPublishedPanel('draft-panel');
        $panel->publicado = false;
        $panel->save();

        $this->get(route('centinela.publico.panel', ['slug' => $panel->slug]))->assertNotFound();
        $this->get(route('centinela.publico.panel', ['slug' => 'no-existe']))->assertNotFound();
        $this->get(route('centinela.storage.public-panel', ['slug' => $panel->slug, 'asset' => 'html']))->assertNotFound();
    }

    public function test_signed_preview_shows_unpublished_panel_and_assets_without_landing(): void
    {
        $panel = $this->createPublishedPanel('preview-ok', 'Panel en revisión');
        $panel->publicado = false;
        $panel->save();

        $this->get(route('centinela.publico.landing'))
            ->assertOk()
            ->assertDontSee('Panel en revisión', false);

        $url = PublicInfoPanelPreview::signedUrl($panel);

        $this->get($url)
            ->assertOk()
            ->assertSee('Panel en revisión', false)
            ->assertSee('Vista de', false)
            ->assertSee('revisión', false)
            ->assertDontSee('Reportar inconsistencia', false);

        $this->get(route('centinela.storage.public-panel', ['slug' => $panel->slug, 'asset' => 'html']))
            ->assertOk();
    }

    public function test_preview_relative_csv_succeeds_without_session_with_asset_signature(): void
    {
        $panel = $this->createPublishedPanel('preview-csv-signed');
        $panel->publicado = false;
        $panel->save();

        $signed = PublicInfoPanelPreview::signedRelativeUrl($panel->slug, 'datos.csv');

        $response = $this->call('GET', $signed, [], []);
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $fileResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $fileResponse);
        $this->assertStringContainsString('a,b', file_get_contents($fileResponse->getFile()->getPathname()));
    }

    public function test_preview_html_injects_signed_base_usable_without_session(): void
    {
        $panel = $this->createPublishedPanel('preview-base-signed', 'Panel base firmada');
        $panel->publicado = false;
        $panel->save();

        $this->get(PublicInfoPanelPreview::signedUrl($panel))->assertOk();

        $html = $this->get($panel->htmlEmbedUrl());
        $html->assertOk();
        $html->assertSee('/storage/public-info-panels/preview-base-signed/p/', false);
        $html->assertSee('/r/', false);

        $this->assertSame(1, preg_match('/<base href="([^"]+)"/i', $html->getContent(), $matches));
        $csvUrl = html_entity_decode($matches[1]).'datos.csv';

        $this->flushSession();
        $this->call('GET', $csvUrl, [], [])->assertOk();
    }

    public function test_preview_relative_csv_without_session_or_signature_is_not_found(): void
    {
        $panel = $this->createPublishedPanel('preview-csv-nosig');
        $panel->publicado = false;
        $panel->save();

        $this->call('GET', route('centinela.storage.public-panel-relative', [
            'slug' => $panel->slug,
            'path' => 'datos.csv',
        ]), [], [])->assertNotFound();
    }

    public function test_preview_asset_signature_tampered_is_not_found(): void
    {
        $panel = $this->createPublishedPanel('preview-csv-tamper');
        $panel->publicado = false;
        $panel->save();

        $url = PublicInfoPanelPreview::signedRelativeUrl($panel->slug, 'datos.csv');
        $tampered = preg_replace('/[a-f0-9]{64}/', str_repeat('a', 64), $url, 1);
        $this->assertIsString($tampered);
        $this->assertNotSame($url, $tampered);

        $this->call('GET', $tampered, [], [])->assertNotFound();
    }

    public function test_preview_asset_signature_expired_is_not_found(): void
    {
        $panel = $this->createPublishedPanel('preview-csv-expired');
        $panel->publicado = false;
        $panel->save();

        $expires = now()->addDays(PublicInfoPanelPreview::ttlDays())->getTimestamp();
        $url = PublicInfoPanelPreview::signedRelativeUrl($panel->slug, 'datos.csv', $expires);

        Carbon::setTestNow(now()->addDays(PublicInfoPanelPreview::ttlDays() + 1));

        $this->call('GET', $url, [], [])->assertNotFound();

        Carbon::setTestNow();
    }

    public function test_preview_signed_asset_redirects_to_login_when_public_dashboard_off(): void
    {
        $panel = $this->createPublishedPanel('preview-csv-off');
        $panel->publicado = false;
        $panel->save();

        $signed = PublicInfoPanelPreview::signedRelativeUrl($panel->slug, 'datos.csv');
        $this->enablePublicDashboard(dash: false, ss: false);

        $this->call('GET', $signed, [], [])->assertRedirect(route('voyager.login'));
    }

    public function test_preview_invalid_signature_is_forbidden(): void
    {
        $panel = $this->createPublishedPanel('preview-bad-sig');
        $panel->publicado = false;
        $panel->save();

        $url = PublicInfoPanelPreview::signedUrl($panel);
        $tampered = $url.(str_contains($url, '?') ? '&' : '?').'x=1';

        $this->get($tampered)->assertForbidden();
    }

    public function test_preview_expired_signature_is_forbidden(): void
    {
        $panel = $this->createPublishedPanel('preview-expired');
        $panel->publicado = false;
        $panel->save();

        $url = URL::temporarySignedRoute(
            'centinela.publico.panel.preview',
            now()->addDays(PublicInfoPanelPreview::ttlDays()),
            ['slug' => $panel->slug],
        );

        Carbon::setTestNow(now()->addDays(PublicInfoPanelPreview::ttlDays() + 1));

        $this->get($url)->assertForbidden();

        Carbon::setTestNow();
    }

    public function test_preview_redirects_to_login_when_public_dashboard_off(): void
    {
        $panel = $this->createPublishedPanel('preview-off');
        $panel->publicado = false;
        $panel->save();

        $url = PublicInfoPanelPreview::signedUrl($panel);
        $this->enablePublicDashboard(dash: false, ss: false);

        $this->get($url)->assertRedirect(route('voyager.login'));
    }

    public function test_preview_mailto_includes_signed_url(): void
    {
        $panel = $this->createPublishedPanel('preview-mail', 'Mailto Panel');
        $panel->publicado = false;
        $panel->save();

        $href = PublicInfoPanelPreview::mailtoHref($panel);

        $this->assertStringStartsWith('mailto:?subject=', $href);
        $this->assertStringContainsString(rawurlencode('Revisión panel: Mailto Panel'), $href);
        $this->assertStringContainsString(rawurlencode('/publico/paneles/preview-mail/preview'), $href);
        $this->assertStringContainsString(rawurlencode('Solo está disponible desde la intranet Minsal.'), $href);
    }

    public function test_panels_paginate_eight_per_page(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createPublishedPanel("panel-{$i}", sprintf('Nacional %02d', $i), $i);
        }

        $page1 = $this->get(route('centinela.publico.landing'));
        $page1->assertOk();
        $page1->assertSee('Nacional 01', false);
        $page1->assertSee('Nacional 08', false);
        $page1->assertDontSee('Nacional 09', false);

        $page2 = $this->get(route('centinela.publico.landing', ['page' => 2]));
        $page2->assertOk();
        $page2->assertSee('Nacional 09', false);
        $page2->assertSee('Nacional 10', false);
        $page2->assertDontSee('Nacional 01', false);
    }

    public function test_show_returns_summary_for_enabled_ss_and_default_year(): void
    {
        $response = $this->get(route('centinela.publico.ss', ['ss' => $this->enabledSs->id]));

        $response->assertOk();
        $response->assertSee('Servicio de Salud Habilitado', false);
        $response->assertSee('COMGES', false);
        $response->assertSee('Metas', false);
        $response->assertSee('2026', false);
        $response->assertSee('Iniciar sesión', false);
    }

    public function test_show_accepts_year_query_and_refreshes_context(): void
    {
        $year2025 = Year::query()->where('ano', 2025)->firstOrFail();

        $response = $this->get(route('centinela.publico.ss', [
            'ss' => $this->enabledSs->id,
            'year' => $year2025->id,
        ]));

        $response->assertOk();
        $response->assertSee('2025', false);
        $this->assertNull(session('indicator_year'));
    }

    public function test_show_returns_404_for_disabled_or_unknown_ss(): void
    {
        $this->get(route('centinela.publico.ss', ['ss' => $this->disabledSs->id]))->assertNotFound();
        $this->get(route('centinela.publico.ss', ['ss' => 999999]))->assertNotFound();
    }

    public function test_show_returns_404_for_invalid_year(): void
    {
        $this->get(route('centinela.publico.ss', [
            'ss' => $this->enabledSs->id,
            'year' => 999999,
        ]))->assertNotFound();

        $this->get(route('centinela.publico.ss', [
            'ss' => $this->enabledSs->id,
            'year' => 'abc',
        ]))->assertNotFound();
    }

    public function test_login_cta_points_to_voyager_login(): void
    {
        $this->get(route('centinela.publico.landing'))
            ->assertOk()
            ->assertSee(route('voyager.login'), false);
    }

    public function test_voyager_login_links_to_public_dashboard(): void
    {
        $this->get(route('voyager.login'))
            ->assertOk()
            ->assertSee(route('centinela.publico.landing'), false)
            ->assertSee('Ver dashboard público', false);
    }

    public function test_voyager_login_hides_public_dashboard_link_when_dash_off(): void
    {
        $this->enablePublicDashboard(dash: false, ss: false);

        $this->get(route('voyager.login'))
            ->assertOk()
            ->assertDontSee('Ver dashboard público', false);
    }

    public function test_ss_logo_storage_route_serves_private_disk_file(): void
    {
        $relative = 'servicios-de-salud/August2026/public-logo-test.png';
        $disk = Storage::disk(config('voyager.storage.disk', 'private'));
        $disk->put($relative, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $this->enabledSs->logo = $relative;
        $this->enabledSs->save();

        $this->get($this->enabledSs->logoPublicUrl())
            ->assertOk();

        $disk->delete($relative);
    }

    public function test_enabling_metas_instrument_invalidates_public_summary_cache(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $route = "metas-20707-{$suffix}";

        $type = IndicatorType::unguarded(fn () => IndicatorType::create([
            'clave_indicador' => $route,
            'nombre_indicador' => "Metas Sanitarias Ley Cache {$suffix}",
            'descripcion_indicador' => 'Test',
            'icon_class' => 'voyager-pie-graph',
            'route' => $route,
            'estado' => false,
            'orden' => 99,
            'measurement_granularity' => 'internal_unit',
        ]));

        ServiceModuleToggle::query()->create([
            'cen_hie_dependency_id' => $this->enabledSs->id,
            'module_key' => $route,
            'enabled' => true,
        ]);

        $service = app(PublicSsDashboardService::class);

        $before = $service->summaryFor($this->enabledSs, $this->year);
        $this->assertNotContains($route, array_column($before['metas'], 'slug'));

        IndicatorTypeSetting::unguarded(fn () => IndicatorTypeSetting::create([
            'indicator_type_id' => $type->id,
            'year_id' => $this->year->id,
            'cen_hie_dependency_id' => $this->enabledSs->id,
            'etapa_vigente' => EtapaVigente::Autoevaluacion,
            'habilitado' => true,
        ]));

        $after = $service->summaryFor($this->enabledSs, $this->year);
        $this->assertContains($route, array_column($after['metas'], 'slug'));
        $this->assertContains("Metas Ley Cache {$suffix}", array_column($after['metas'], 'label'));

        $this->get(route('centinela.publico.ss', ['ss' => $this->enabledSs->id]))
            ->assertOk()
            ->assertSee('class="semaforo', false);
    }

    private function enablePublicDashboard(bool $dash = true, bool $ss = true): void
    {
        $config = app(ConfigurationService::class);
        $config->setRadioOn(PublicDashboardGate::SETTING_KEY, $dash);
        $config->setRadioOn(PublicSsDashboardGate::SETTING_KEY, $ss);
        $config->invalidate();
    }

    /**
     * @param  array<string, string>  $files
     */
    private function makeTempZip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pip-zip-');
        $this->assertNotFalse($path);
        $zipPath = $path.'.zip';
        @unlink($path);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        $this->assertFileExists($zipPath);

        return $zipPath;
    }

    private function createPublishedPanel(string $slug, ?string $titulo = null, int $orden = 0): PublicInfoPanel
    {
        $disk = Storage::disk(config('voyager.storage.disk', 'private'));
        $html = "public-info-panels/html/{$slug}.html";
        $csv = "public-info-panels/csv/{$slug}.csv";
        $png = "public-info-panels/preview/{$slug}.png";

        $disk->put($html, '<html><body><h1>Panel</h1></body></html>');
        $disk->put($csv, "a,b\n1,2\n");
        $disk->put($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        return PublicInfoPanel::unguarded(fn () => PublicInfoPanel::create([
            'slug' => $slug,
            'tipo' => PublicInfoPanel::TIPO_HTML_BUNDLE,
            'titulo' => $titulo ?? "Título {$slug}",
            'descripcion' => 'Descripción de prueba',
            'html_path' => $html,
            'csv_path' => $csv,
            'preview_png_path' => $png,
            'orden' => $orden,
            'publicado' => true,
        ]));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function createPublishedEmbedPanel(
        string $slug,
        string $embedUrl,
        ?string $titulo = null,
        array $meta = [],
        int $orden = 0,
    ): PublicInfoPanel {
        $disk = Storage::disk(config('voyager.storage.disk', 'private'));
        $png = "public-info-panels/preview/{$slug}.png";
        $disk->put($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        return PublicInfoPanel::unguarded(fn () => PublicInfoPanel::create(array_merge([
            'slug' => $slug,
            'tipo' => PublicInfoPanel::TIPO_EMBED_EXTERNO,
            'titulo' => $titulo ?? "Título {$slug}",
            'descripcion' => 'Descripción de prueba embed',
            'embed_url' => $embedUrl,
            'preview_png_path' => $png,
            'orden' => $orden,
            'publicado' => true,
        ], $meta)));
    }
}
