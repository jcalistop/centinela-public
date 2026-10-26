<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Github;

use App\Services\Github\GithubReleasesService;
use App\Services\Releases\DeployedReleaseState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubReleasesApiProbeCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_api_probe_caches_successful_result_within_ttl(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([['id' => 1, 'tag_name' => 'v1.0.0']], 200),
        ]);

        $service = app(GithubReleasesService::class);

        $this->assertTrue($service->apiProbeSucceeded());
        $this->assertTrue($service->apiProbeSucceeded());

        Http::assertSentCount(1);
    }

    public function test_api_probe_caches_failed_result_within_ttl(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response('unavailable', 503),
        ]);

        $service = new GithubReleasesService(app(DeployedReleaseState::class));

        $this->assertFalse($service->apiProbeSucceeded());
        $this->assertFalse($service->apiProbeSucceeded());

        Http::assertSentCount(1);
    }
}
