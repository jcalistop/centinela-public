<?php

namespace Tests\Feature\AcercaDe;

use App\Models\CenHieDependency;
use App\Models\DeployExecution;
use App\Models\User;
use App\Models\Year;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubReleasesPageTest extends TestCase
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

    protected function tearDown(): void
    {
        Cache::flush();
        Http::fake();

        parent::tearDown();
    }

    private function seedDeployedCap(string $version): void
    {
        DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'meta' => [
                'deployed_version' => $version,
                'deployed_at' => now()->toIso8601String(),
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    /** @test */
    public function releases_page_filters_by_deployed_version_cap(): void
    {
        Cache::flush();
        $this->seedDeployedCap('v1.0.0');
        config()->set('services.github.releases_repository', 'owner/test-repo');
        config()->set('services.github.releases_http_cache_seconds', 3600);

        Http::fake([
            'https://api.github.com/repos/owner/test-repo/releases' => Http::response([
                [
                    'id' => 2,
                    'tag_name' => 'v2.0.0',
                    'name' => 'Second',
                    'published_at' => '2025-06-01T12:00:00Z',
                    'body' => '## Dos',
                ],
                [
                    'id' => 1,
                    'tag_name' => 'v1.0.0',
                    'name' => 'First',
                    'published_at' => '2024-01-15T12:00:00Z',
                    'body' => '## Uno',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $response = $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.releases'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('v1.0.0', $html);
        $this->assertStringNotContainsString('v2.0.0', $html);
        $this->assertStringContainsString('Versión en producción', $html);
    }

    /** @test */
    public function releases_page_shows_github_payload_with_single_open_details(): void
    {
        Cache::flush();
        $this->seedDeployedCap('v2.0.0');
        config()->set('services.github.releases_repository', 'owner/test-repo');
        config()->set('services.github.releases_http_cache_seconds', 3600);

        Http::fake([
            'https://api.github.com/repos/owner/test-repo/releases' => Http::response([
                [
                    'id' => 2,
                    'tag_name' => 'v2.0.0',
                    'name' => 'Second',
                    'published_at' => '2025-06-01T12:00:00Z',
                    'body' => '## Dos',
                ],
                [
                    'id' => 1,
                    'tag_name' => 'v1.0.0',
                    'name' => 'First',
                    'published_at' => '2024-01-15T12:00:00Z',
                    'body' => '## Uno',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $response = $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.releases'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('id="release-2"', $html);
        $this->assertStringContainsString('v2.0.0', $html);
        $this->assertStringContainsString('v1.0.0', $html);
        $this->assertMatchesRegularExpression('/<details[^>]*\bopen\b/i', $html);
        $this->assertEquals(1, preg_match_all('/<details[^>]*\bopen\b/iu', $html));
        $this->assertEquals(2, substr_count($html, 'class="release-block"'));
        $this->assertEquals(2, substr_count($html, '<details'));
    }

    /** @test */
    public function releases_page_renders_github_flavored_markdown_tables(): void
    {
        Cache::flush();
        $this->seedDeployedCap('v9.0.0');
        config()->set('services.github.releases_repository', 'owner/test-repo');
        config()->set('services.github.releases_http_cache_seconds', 3600);

        $bodyWithTable = <<<'MD'
## Cabecera

| Campo | Valor |
| ----- | ----- |
| **Versión** | v9.0.0 |

Texto posterior.
MD;

        Http::fake([
            'https://api.github.com/repos/owner/test-repo/releases' => Http::response([
                [
                    'id' => 10,
                    'tag_name' => 'v9.0.0',
                    'name' => 'Table test',
                    'published_at' => '2026-05-06T12:00:00Z',
                    'body' => $bodyWithTable,
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $response = $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.releases'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('<td', $html);
        $this->assertStringContainsString('v9.0.0', $html);
    }

    /** @test */
    public function releases_page_serves_stale_cache_when_github_fails(): void
    {
        Cache::flush();
        $this->seedDeployedCap('v9.9.9');
        config()->set('services.github.releases_repository', 'owner/test-repo');

        $synced = CarbonImmutable::parse('2025-01-01T10:00:00Z');
        Cache::put('github.releases:last_good', [
            'releases' => [[
                'id' => 99,
                'tag_name' => 'v9.9.9',
                'name' => 'Stale',
                'published_at' => '2025-01-01T10:00:00Z',
                'display_date' => '01/01/2025',
                'body' => 'Notas stale',
            ]],
            'synced_at' => $synced->toIso8601String(),
        ], now()->addHour());

        Http::fake([
            'https://api.github.com/repos/owner/test-repo/releases' => Http::response('', 503),
        ]);

        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $response = $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.releases'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('No se pudo contactar a GitHub', $html);
        $this->assertStringContainsString('v9.9.9', $html);
        $this->assertStringContainsString('Notas stale', $html);
    }

    /** @test */
    public function releases_page_degrades_when_github_fails_and_no_cache(): void
    {
        Cache::flush();
        config()->set('services.github.releases_repository', 'owner/test-repo');

        Http::fake([
            'https://api.github.com/repos/owner/test-repo/releases' => Http::response('', 503),
        ]);

        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $response = $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.releases'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('No hay historial de versiones disponible', $html);
        $this->assertStringContainsString('Sin versión desplegada registrada', $html);
        $this->assertStringContainsString('class="alert alert-warning releases-alert"', $html);
        $this->assertStringNotContainsString('class="alert alert-success releases-alert"', $html);
    }
}
