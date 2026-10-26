<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Releases;

use App\Models\DeployExecution;
use App\Services\Releases\DeployedReleaseState;
use App\Support\Deploy\DeployedVersionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DeployedReleaseStateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /** @test */
    public function deployed_version_prefers_meta_over_sha_on_http(): void
    {
        config(['centinela.deploy.git_status_via_http' => false]);

        $resolver = Mockery::mock(DeployedVersionResolver::class);
        $resolver->shouldNotReceive('resolveTagFromSha');

        DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'to_sha' => 'abc123',
            'meta' => ['deployed_version' => 'v4.4.4'],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $state = new DeployedReleaseState($resolver);

        $this->assertSame('v4.4.4', $state->deployedVersion());
    }

    /** @test */
    public function deployed_version_resolves_sha_when_meta_missing_and_git_allowed(): void
    {
        config(['centinela.deploy.git_status_via_http' => true]);

        $resolver = Mockery::mock(DeployedVersionResolver::class);
        $resolver->shouldReceive('resolveTagFromSha')
            ->once()
            ->with('abc123')
            ->andReturn('v4.4.2');

        DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'to_sha' => 'abc123',
            'meta' => [],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $state = new DeployedReleaseState($resolver);

        $this->assertSame('v4.4.2', $state->deployedVersion());
    }

    /** @test */
    public function deployed_version_returns_null_when_meta_and_sha_unavailable(): void
    {
        config(['centinela.deploy.git_status_via_http' => true]);

        $resolver = Mockery::mock(DeployedVersionResolver::class);
        $resolver->shouldReceive('resolveTagFromSha')
            ->once()
            ->with('abc123')
            ->andReturn(null);

        DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'to_sha' => 'abc123',
            'meta' => [],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $state = new DeployedReleaseState($resolver);

        $this->assertNull($state->deployedVersion());
    }

    /** @test */
    public function deployed_version_ignores_app_version_env(): void
    {
        config(['centinela.deploy.git_status_via_http' => false]);
        putenv('APP_VERSION=v3.2.1');

        $resolver = Mockery::mock(DeployedVersionResolver::class);
        $resolver->shouldNotReceive('resolveTagFromSha');

        DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'to_sha' => 'abc123',
            'meta' => [],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $state = new DeployedReleaseState($resolver);

        $this->assertNull($state->deployedVersion());
        $this->assertSame('', $state->displayVersion());

        putenv('APP_VERSION');
    }
}
