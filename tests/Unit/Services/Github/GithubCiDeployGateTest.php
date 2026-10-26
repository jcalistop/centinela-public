<?php

namespace Tests\Unit\Services\Github;

use App\Services\Github\GithubCiStatusService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubCiDeployGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('github.ci:latest_main_run:v1');
    }

    /** @test */
    public function gate_off_allows_any_ci_state(): void
    {
        config([
            'centinela.deploy.require_ci_mode' => 'none',
            'services.github.releases_repository' => 'org/app',
            'centinela.deploy.branch' => 'main',
        ]);

        Http::fake([
            'api.github.com/*' => Http::response([
                'workflow_runs' => [[
                    'status' => 'completed',
                    'conclusion' => 'failure',
                    'head_sha' => 'deadbeef',
                    'html_url' => 'https://github.com/org/app/actions/runs/1',
                    'name' => 'CI',
                    'created_at' => '2026-08-07T12:00:00Z',
                ]],
            ], 200),
        ]);

        $reason = app(GithubCiStatusService::class)->deployGateBlockReason('auto');

        $this->assertNull($reason);
    }

    /** @test */
    public function gate_all_blocks_failure(): void
    {
        config([
            'centinela.deploy.require_ci_mode' => 'all',
            'services.github.releases_repository' => 'org/app',
            'centinela.deploy.branch' => 'main',
        ]);

        Http::fake([
            'api.github.com/*' => Http::response([
                'workflow_runs' => [[
                    'status' => 'completed',
                    'conclusion' => 'failure',
                    'head_sha' => 'deadbeef01234567',
                    'html_url' => 'https://github.com/org/app/actions/runs/2',
                    'name' => 'CI',
                    'created_at' => '2026-08-07T12:00:00Z',
                ]],
            ], 200),
        ]);

        $reason = app(GithubCiStatusService::class)->deployGateBlockReason('manual');

        $this->assertNotNull($reason);
        $this->assertStringContainsString('Gate CI', $reason);
        $this->assertStringContainsString('failure', $reason);
    }

    /** @test */
    public function gate_auto_allows_manual_when_ci_red(): void
    {
        config([
            'centinela.deploy.require_ci_mode' => 'auto',
            'services.github.releases_repository' => 'org/app',
            'centinela.deploy.branch' => 'main',
        ]);

        Http::fake([
            'api.github.com/*' => Http::response([
                'workflow_runs' => [[
                    'status' => 'completed',
                    'conclusion' => 'failure',
                    'head_sha' => 'deadbeef01234567',
                    'html_url' => 'https://github.com/org/app/actions/runs/3',
                    'name' => 'CI',
                    'created_at' => '2026-08-07T12:00:00Z',
                ]],
            ], 200),
        ]);

        $this->assertNull(app(GithubCiStatusService::class)->deployGateBlockReason('manual'));
        $this->assertNotNull(app(GithubCiStatusService::class)->deployGateBlockReason('auto'));
    }

    /** @test */
    public function gate_all_allows_success(): void
    {
        config([
            'centinela.deploy.require_ci_mode' => 'all',
            'centinela.deploy.require_ci_match_sha' => false,
            'services.github.releases_repository' => 'org/app',
            'centinela.deploy.branch' => 'main',
        ]);

        Http::fake([
            'api.github.com/*' => Http::response([
                'workflow_runs' => [[
                    'status' => 'completed',
                    'conclusion' => 'success',
                    'head_sha' => 'abc123def456789',
                    'html_url' => 'https://github.com/org/app/actions/runs/4',
                    'name' => 'CI',
                    'created_at' => '2026-08-07T12:00:00Z',
                ]],
            ], 200),
        ]);

        $this->assertNull(app(GithubCiStatusService::class)->deployGateBlockReason('manual'));
    }

    /** @test */
    public function unknown_does_not_block_unless_strict(): void
    {
        config([
            'centinela.deploy.require_ci_mode' => 'all',
            'centinela.deploy.require_ci_block_unknown' => false,
            'services.github.releases_repository' => 'org/app',
        ]);

        Http::fake([
            'api.github.com/*' => Http::response('error', 500),
        ]);

        $this->assertNull(app(GithubCiStatusService::class)->deployGateBlockReason('auto'));

        Cache::forget('github.ci:latest_main_run:v1');
        config(['centinela.deploy.require_ci_block_unknown' => true]);

        $reason = app(GithubCiStatusService::class)->deployGateBlockReason('auto');
        $this->assertNotNull($reason);
        $this->assertStringContainsString('estricto', $reason);
    }
}
