<?php

namespace Tests\Unit\Support\Deploy;

use App\Models\DeployExecution;
use App\Models\User;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class DeployOrchestratorIdempotentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @param  callable(MockInterface&DeployOrchestrator): void  $configure
     */
    private function partialDeployOrchestrator(callable $configure): DeployOrchestrator
    {
        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock) use ($configure): void {
            $mock->shouldAllowMockingProtectedMethods();
            $configure($mock);
        });

        return $orchestrator;
    }

    /** @test */
    public function assess_idempotent_deploy_skips_when_sha_aligned_and_no_migrations(): void
    {
        DeployExecution::query()->create([
            'mode' => 'auto',
            'status' => 'succeeded',
            'to_sha' => 'abc123',
            'branch' => 'main',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $orchestrator = $this->partialDeployOrchestrator(function (MockInterface $orchestrator): void {
            $orchestrator->shouldReceive('tryGitRevParse')->with('HEAD')->andReturn('abc123');
            $orchestrator->shouldReceive('fetchRemoteGitState')->with('abc123')->andReturn([
                'remote_sha' => 'def456',
                'commits_ahead_on_remote' => 0,
            ]);
            $orchestrator->shouldReceive('hasPendingMigrations')->andReturn(false);
        });

        $assessment = $orchestrator->assessIdempotentDeploy();

        $this->assertTrue($assessment['skip']);
        $this->assertSame('abc123', $assessment['local_sha']);
        $this->assertSame(0, $assessment['commits_ahead_on_remote']);
    }

    /** @test */
    public function assess_idempotent_deploy_does_not_skip_when_remote_is_ahead(): void
    {
        DeployExecution::query()->create([
            'mode' => 'auto',
            'status' => 'succeeded',
            'to_sha' => 'abc123',
            'branch' => 'main',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $orchestrator = $this->partialDeployOrchestrator(function (MockInterface $orchestrator): void {
            $orchestrator->shouldReceive('tryGitRevParse')->with('HEAD')->andReturn('abc123');
            $orchestrator->shouldReceive('fetchRemoteGitState')->with('abc123')->andReturn([
                'remote_sha' => 'def456',
                'commits_ahead_on_remote' => 2,
            ]);
            $orchestrator->shouldReceive('hasPendingMigrations')->andReturn(false);
        });

        $this->assertFalse($orchestrator->assessIdempotentDeploy()['skip']);
    }

    /** @test */
    public function assess_idempotent_deploy_does_not_skip_when_migrations_pending(): void
    {
        DeployExecution::query()->create([
            'mode' => 'manual',
            'status' => 'succeeded',
            'to_sha' => 'abc123',
            'branch' => 'main',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $orchestrator = $this->partialDeployOrchestrator(function (MockInterface $orchestrator): void {
            $orchestrator->shouldReceive('tryGitRevParse')->with('HEAD')->andReturn('abc123');
            $orchestrator->shouldReceive('fetchRemoteGitState')->with('abc123')->andReturn([
                'remote_sha' => 'abc123',
                'commits_ahead_on_remote' => 0,
            ]);
            $orchestrator->shouldReceive('hasPendingMigrations')->andReturn(true);
        });

        $this->assertFalse($orchestrator->assessIdempotentDeploy()['skip']);
    }

    /** @test */
    public function manual_run_records_skipped_execution_without_backup(): void
    {
        $actor = User::factory()->create();

        $orchestrator = $this->partialDeployOrchestrator(function (MockInterface $orchestrator): void {
            $orchestrator->shouldReceive('branchMismatchSummary')->andReturn(null);
            $orchestrator->shouldReceive('assessIdempotentDeploy')->andReturn([
                'skip' => true,
                'local_sha' => 'sha-local',
                'remote_sha' => 'other-remote',
                'last_success_sha' => 'sha-local',
                'commits_ahead_on_remote' => 0,
                'has_pending_migrations' => false,
            ]);
        });

        $result = $orchestrator->runManual($actor);

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('deploy_executions', [
            'mode' => 'manual',
            'status' => 'skipped',
            'from_sha' => 'sha-local',
            'to_sha' => 'sha-local',
            'backup_status' => 'skipped',
            'migrate_status' => 'skipped',
        ]);
        $this->assertSame(1, DeployExecution::query()->count());
    }

    /** @test */
    public function has_pending_migrations_is_false_when_database_is_up_to_date(): void
    {
        $this->artisan('migrate', ['--force' => true]);

        $orchestrator = new DeployOrchestrator;
        $method = new \ReflectionMethod(DeployOrchestrator::class, 'hasPendingMigrations');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($orchestrator));
    }

    /** @test */
    public function has_pending_migrations_includes_app_migration_path_when_packages_register_paths(): void
    {
        $this->artisan('migrate', ['--force' => true]);

        $migrationPath = database_path('migrations/2026_06_10_999999_deploy_pending_detection_test.php');
        file_put_contents($migrationPath, <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
PHP);

        try {
            $orchestrator = new DeployOrchestrator;
            $method = new \ReflectionMethod(DeployOrchestrator::class, 'hasPendingMigrations');
            $method->setAccessible(true);

            $this->assertTrue($method->invoke($orchestrator));
        } finally {
            @unlink($migrationPath);
        }
    }

    /** @test */
    public function auto_run_records_skipped_and_sets_daily_cache(): void
    {
        config(['centinela.deploy.automatic_enabled' => true]);

        $orchestrator = $this->partialDeployOrchestrator(function (MockInterface $orchestrator): void {
            $orchestrator->shouldReceive('branchMismatchSummary')->andReturn(null);
            $orchestrator->shouldReceive('assessIdempotentDeploy')->andReturn([
                'skip' => true,
                'local_sha' => 'sha-local',
                'remote_sha' => 'other-remote',
                'last_success_sha' => 'sha-local',
                'commits_ahead_on_remote' => 0,
                'has_pending_migrations' => false,
            ]);
        });

        $result = $orchestrator->runAuto();

        $this->assertTrue($result['ok']);
        $this->assertSame(now()->toDateString(), cache('centinela:deploy:auto:last_success_date:v1'));
        $this->assertDatabaseHas('deploy_executions', ['mode' => 'auto', 'status' => 'skipped']);
    }
}
