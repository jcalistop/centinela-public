<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\DeployExecution;
use Tests\TestCase;

class DeployExecutionVersionTest extends TestCase
{
    /** @test */
    public function display_version_reads_deployed_version_for_deploy_modes(): void
    {
        $execution = new DeployExecution([
            'mode' => 'manual',
            'meta' => ['deployed_version' => '4.4.5'],
        ]);

        $this->assertSame('v4.4.5', $execution->displayVersion());
    }

    /** @test */
    public function display_version_reads_notified_version_for_notification_modes(): void
    {
        $execution = new DeployExecution([
            'mode' => 'release_notify',
            'meta' => ['notified_version' => 'v4.4.5'],
        ]);

        $this->assertSame('v4.4.5', $execution->displayVersion());
    }

    /** @test */
    public function display_version_returns_null_when_meta_missing(): void
    {
        $execution = new DeployExecution([
            'mode' => 'manual',
            'meta' => [],
        ]);

        $this->assertNull($execution->displayVersion());
    }
}
