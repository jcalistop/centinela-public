<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Deploy;

use App\Support\Deploy\DeployOrchestrator;
use Mockery\MockInterface;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeployOrchestratorPostComposerCliTest extends TestCase
{
    /** @test */
    public function run_migrate_uses_artisan_cli_subprocess_when_pending(): void
    {
        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('hasPendingMigrations')->once()->andReturn(true);
            $mock->shouldReceive('runArtisanCli')
                ->once()
                ->with('migrate', ['--force' => true], 900)
                ->andReturn(['ok' => true, 'exit_code' => 0, 'output' => 'Migrated.']);
        });

        $method = new \ReflectionMethod(DeployOrchestrator::class, 'runMigrate');
        $method->setAccessible(true);

        $result = $method->invoke($orchestrator);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['ran']);
        $this->assertStringContainsString('Migrated', $result['summary']);
    }

    /** @test */
    public function run_migrate_marks_failed_when_cli_fails_without_claiming_ran_false_on_exception_path(): void
    {
        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('hasPendingMigrations')->once()->andReturn(true);
            $mock->shouldReceive('runArtisanCli')
                ->once()
                ->with('migrate', ['--force' => true], 900)
                ->andReturn([
                    'ok' => false,
                    'exit_code' => 1,
                    'output' => 'Call to undefined method Task::runTimeForHumans()',
                ]);
        });

        $method = new \ReflectionMethod(DeployOrchestrator::class, 'runMigrate');
        $method->setAccessible(true);

        $result = $method->invoke($orchestrator);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['ran']);
        $this->assertStringContainsString('runTimeForHumans', $result['summary']);
    }

    /** @test */
    public function rollback_runs_composer_install_after_git_reset_when_code_changed(): void
    {
        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('runGit')
                ->once()
                ->with(['reset', '--hard', 'abc123'], 60)
                ->andReturnUsing(fn (): Process => $this->fakeSuccessfulGitProcess('HEAD is now at abc123'));
            $mock->shouldReceive('runComposerInstall')
                ->once()
                ->andReturn(['ok' => true, 'exit_code' => 0, 'output' => 'composer install OK']);
        });

        $method = new \ReflectionMethod(DeployOrchestrator::class, 'rollback');
        $method->setAccessible(true);

        $result = $method->invoke($orchestrator, 'abc123', true, false);

        $this->assertTrue($result['ok']);
        $this->assertContains('git reset --hard abc123', $result['actions']);
        $this->assertContains(
            'composer install --no-dev --optimize-autoloader --no-interaction',
            $result['actions']
        );
    }

    /** @test */
    public function rollback_fails_when_composer_install_after_reset_fails(): void
    {
        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('runGit')
                ->once()
                ->andReturnUsing(fn (): Process => $this->fakeSuccessfulGitProcess('HEAD is now at abc123'));
            $mock->shouldReceive('runComposerInstall')
                ->once()
                ->andReturn(['ok' => false, 'exit_code' => 1, 'output' => 'Your requirements could not be resolved']);
        });

        $method = new \ReflectionMethod(DeployOrchestrator::class, 'rollback');
        $method->setAccessible(true);

        $result = $method->invoke($orchestrator, 'abc123', true, false);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('composer install', $result['summary']);
    }

    /** @test */
    public function rollback_uses_artisan_cli_for_migrate_rollback_when_migrations_ran(): void
    {
        /** @var DeployOrchestrator $orchestrator */
        $orchestrator = $this->partialMock(DeployOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('runArtisanCli')
                ->once()
                ->with('migrate:rollback', ['--force' => true], 600)
                ->andReturn(['ok' => true, 'exit_code' => 0, 'output' => 'Rolled back.']);
            $mock->shouldReceive('runGit')->never();
            $mock->shouldReceive('runComposerInstall')->never();
        });

        $method = new \ReflectionMethod(DeployOrchestrator::class, 'rollback');
        $method->setAccessible(true);

        $result = $method->invoke($orchestrator, null, false, true);

        $this->assertTrue($result['ok']);
        $this->assertContains('php artisan migrate:rollback --force', $result['actions']);
    }

    private function fakeSuccessfulGitProcess(string $output): Process
    {
        /** @var Process&MockInterface $process */
        $process = \Mockery::mock(Process::class);
        $process->shouldReceive('isSuccessful')->andReturn(true);
        $process->shouldReceive('getErrorOutput')->andReturn('');
        $process->shouldReceive('getOutput')->andReturn($output);

        return $process;
    }
}
