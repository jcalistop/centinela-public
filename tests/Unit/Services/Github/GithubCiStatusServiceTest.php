<?php

namespace Tests\Unit\Services\Github;

use App\Services\Github\GithubCiStatusService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubCiStatusServiceTest extends TestCase
{
    /** @test */
    public function it_maps_successful_completed_run(): void
    {
        config(['services.github.releases_repository' => 'org/app', 'centinela.deploy.branch' => 'main']);

        Http::fake([
            'api.github.com/repos/org/app/actions/runs*' => Http::response([
                'workflow_runs' => [[
                    'status' => 'completed',
                    'conclusion' => 'success',
                    'head_sha' => 'abc123def456789',
                    'html_url' => 'https://github.com/org/app/actions/runs/1',
                    'name' => 'CI',
                    'created_at' => '2026-05-28T12:00:00Z',
                ]],
            ], 200),
        ]);

        $status = app(GithubCiStatusService::class)->latestForDeployBranch();

        $this->assertSame('success', $status->state);
        $this->assertSame('abc123def456789', $status->headSha);
        $this->assertSame('abc123d', $status->headShaShort);
        $this->assertSame('https://github.com/org/app/actions/runs/1', $status->htmlUrl);
        $this->assertFalse($status->stale);
    }

    /** @test */
    public function it_returns_unknown_when_api_fails(): void
    {
        config(['services.github.releases_repository' => 'org/app']);

        Http::fake([
            'api.github.com/*' => Http::response('error', 500),
        ]);

        $status = app(GithubCiStatusService::class)->latestForDeployBranch();

        $this->assertSame('unknown', $status->state);
        $this->assertNotNull($status->errorMessage);
    }
}
