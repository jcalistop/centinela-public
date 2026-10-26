<?php

declare(strict_types=1);

namespace Tests\Feature\Releases;

use App\Models\CenHieDependency;
use App\Models\DeployExecution;
use App\Models\GithubReleaseSnapshot;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubReleaseSnapshotTest extends TestCase
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

    /** @test */
    public function releases_page_reads_snapshots_after_cache_clear_when_api_fails(): void
    {
        DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'meta' => [
                'deployed_version' => 'v4.4.0',
                'deployed_at' => now()->toIso8601String(),
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        GithubReleaseSnapshot::upsertFromNormalizedRelease([
            'id' => 44,
            'tag_name' => 'v4.4.0',
            'name' => 'Cuatro cuatro',
            'published_at' => '2026-05-01T12:00:00Z',
            'display_date' => '01/05/2026',
            'body' => '## Notas desde snapshot BD',
        ]);

        GithubReleaseSnapshot::upsertFromNormalizedRelease([
            'id' => 45,
            'tag_name' => 'v4.5.0',
            'name' => 'Futuro',
            'published_at' => '2026-06-01T12:00:00Z',
            'display_date' => '01/06/2026',
            'body' => 'No debe mostrarse',
        ]);

        Cache::flush();

        Http::fake([
            'https://api.github.com/repos/*' => Http::response('', 503),
        ]);

        config()->set('services.github.releases_repository', 'owner/test-repo');

        $user = User::factory()->create();
        $year = Year::getDefaultYear();

        $response = $this->actingAs($user)
            ->withSession(['indicator_year' => $year])
            ->get(route('acerca-de.releases'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('Notas desde snapshot BD', $html);
        $this->assertStringContainsString('v4.4.0', $html);
        $this->assertStringNotContainsString('No debe mostrarse', $html);
        $this->assertStringContainsString('No se pudo contactar a GitHub', $html);
    }
}
