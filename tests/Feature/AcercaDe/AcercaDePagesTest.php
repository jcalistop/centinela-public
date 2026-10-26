<?php

namespace Tests\Feature\AcercaDe;

use App\Models\CenHieDependency;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcercaDePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Year::unguarded(fn () => Year::create([
            'ano' => 2026,
            'default' => true,
        ]));

        CenHieDependency::unguarded(fn () => CenHieDependency::create([
            'id' => 1,
            'nombre' => 'Servicio de Salud de Prueba',
            'tipo_dependencia' => 'SERVICIO',
            'habilitado' => true,
        ]));
    }

    /** @test */
    public function landing_shows_resumen_and_support_cta(): void
    {
        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.index'))
            ->assertOk()
            ->assertSee('Resumen', false)
            ->assertSee('herramienta de gestión', false)
            ->assertSee('Servicio de Salud de Reloncaví', false)
            ->assertSee('/admin/soporte', false)
            ->assertSee('Visión funcional', false)
            ->assertSee('/docs/diagrams/vision-funcional.svg', false)
            ->assertDontSee('Solicitud de información o contacto', false)
            ->assertDontSee('id="contacto"', false);
    }

    /** @test */
    public function subpages_render_with_subnav(): void
    {
        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $routes = [
            ['acerca-de.modulos', 'Módulos de la plataforma', 'Desarrollo en proceso, no liberado'],
            ['acerca-de.roles', 'Roles y contexto de operación', 'responsable de establecimiento'],
            ['acerca-de.ciclo-comges', 'Ciclo COMGES', '/docs/diagrams/ciclo-comges.svg'],
            ['acerca-de.ciclo-metas', 'Ciclo Metas', '/docs/diagrams/ciclo-metas.svg'],
            ['acerca-de.adopcion', 'Adopción', 'Araucanía Sur'],
            ['acerca-de.tecnica', 'Información técnica', 'Laravel'],
        ];

        foreach ($routes as [$routeName, $heading, $snippet]) {
            $response = $this->actingAs($user)
                ->withSession(['indicator_year' => $year])
                ->get(route($routeName));

            $response->assertOk()
                ->assertSee($heading, false)
                ->assertSee($snippet, false)
                ->assertSee('cen-dashboard-block', false);

            if (in_array($routeName, ['acerca-de.adopcion', 'acerca-de.tecnica'], true)) {
                $response->assertSee('/admin/acerca-de/releases', false);
            } else {
                $response->assertDontSee('Versiones liberadas', false);
            }
        }
    }

    /** @test */
    public function subnav_shows_split_ciclo_tabs(): void
    {
        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.ciclo-comges'))
            ->assertOk()
            ->assertSee('Ciclo COMGES', false)
            ->assertSee('Ciclo Metas', false)
            ->assertDontSee('Ciclo de vida', false);
    }

    /** @test */
    public function legacy_ciclo_route_redirects_to_comges(): void
    {
        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/acerca-de/ciclo')
            ->assertRedirect(route('acerca-de.ciclo-comges'));
    }

    /** @test */
    public function modulos_page_describes_comges_cortes_by_oott(): void
    {
        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.modulos'))
            ->assertOk()
            ->assertSee('cortes según OOTT', false)
            ->assertDontSee('tres cortes', false);
    }

    /** @test */
    public function releases_page_is_separate_from_acerca_de_subnav(): void
    {
        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.releases'))
            ->assertOk()
            ->assertSee('Versiones liberadas', false)
            ->assertSee('cen-dashboard-block', false)
            ->assertDontSee('aria-label="Secciones ¿Qué es Centinela?"', false);
    }

    /** @test */
    public function ciclo_comges_page_documents_comges_only(): void
    {
        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.ciclo-comges'))
            ->assertOk()
            ->assertSee('/docs/diagrams/ciclo-comges.svg', false)
            ->assertSee('monitoreos', false)
            ->assertDontSee('/docs/diagrams/ciclo-metas.svg', false)
            ->assertDontSee('DIAGRAMA-PENDIENTE-ARCHIFY: ciclo-comges', false);
    }

    /** @test */
    public function ciclo_metas_page_documents_metas_only(): void
    {
        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.ciclo-metas'))
            ->assertOk()
            ->assertSee('/docs/diagrams/ciclo-metas.svg', false)
            ->assertSee('Formulación', false)
            ->assertDontSee('/docs/diagrams/ciclo-comges.svg', false)
            ->assertDontSee('DIAGRAMA-PENDIENTE-ARCHIFY: ciclo-metas', false);
    }

    /** @test */
    public function tecnica_page_shows_technical_architecture_diagram_only(): void
    {
        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.tecnica'))
            ->assertOk()
            ->assertSee('Visión técnica', false)
            ->assertSee('/docs/diagrams/vision-tecnica.svg', false)
            ->assertDontSee('/docs/diagrams/vision-funcional.svg', false);
    }

    /** @test */
    public function diagram_and_panel_links_respect_app_url_subdirectory(): void
    {
        // Mismo host que phpunit (TrustHosts); solo cambia el path de APP_URL (IIS /centinela).
        config(['app.url' => 'http://localhost/centinela']);

        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/acerca-de')
            ->assertOk()
            ->assertSee('src="/centinela/docs/diagrams/vision-funcional.svg"', false)
            ->assertSee('href="/centinela/admin/soporte"', false)
            ->assertDontSee('src="/docs/diagrams/vision-funcional.svg"', false);
    }

    /** @test */
    public function invalid_slug_returns_not_found(): void
    {
        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get('/admin/acerca-de/no-existe')
            ->assertNotFound();
    }

    /** @test */
    public function contact_route_is_removed(): void
    {
        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->post('/admin/acerca-de/contacto', [])
            ->assertNotFound();
    }
}
