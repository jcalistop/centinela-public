<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\DeployExecution;
use App\Support\CentinelaSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentinelaSessionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function resolve_deployed_version_label_uses_last_successful_deploy(): void
    {
        DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'meta' => ['deployed_version' => 'v4.4.5'],
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour(),
        ]);

        $this->assertSame('v4.4.5', CentinelaSession::resolveDeployedVersionLabel());
    }

    /** @test */
    public function resolve_deployed_version_label_falls_back_to_app_version_when_no_deploy(): void
    {
        config(['app.version' => 'v4.3.0']);

        $this->assertSame('v4.3.0', CentinelaSession::resolveDeployedVersionLabel());
    }
}
