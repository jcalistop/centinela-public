<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Deploy;

use App\Support\Deploy\DeployGitPullWorktreeGuard;
use ArrayObject;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeployGitPullWorktreeGuardTest extends TestCase
{
    /** @test */
    public function prepare_skips_untracked_generated_path_without_warning(): void
    {
        $warnings = $this->captureRestoreWarnings();
        $calls = [];

        $guard = new DeployGitPullWorktreeGuard(function (array $args, int $timeout) use (&$calls): Process {
            $calls[] = $args;

            return $this->successfulGit('');
        });

        $result = $guard->prepare('origin', 'main', ['public/vendor/livewire/']);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['restored']);
        $this->assertSame([], $this->restoreCalls($calls));
        $this->assertCount(0, $warnings);
    }

    /** @test */
    public function prepare_restores_tracked_path_deleted_upstream(): void
    {
        $calls = [];
        $tracked = "public/vendor/livewire/livewire.js\n";

        $guard = new DeployGitPullWorktreeGuard(function (array $args, int $timeout) use (&$calls, $tracked): Process {
            $calls[] = $args;

            if ($args[0] === 'diff' || $args[0] === 'ls-files') {
                return $this->successfulGit($tracked);
            }

            return $this->successfulGit('');
        });

        $result = $guard->prepare('origin', 'main', []);

        $this->assertTrue($result['ok']);
        $this->assertContains('public/vendor/livewire/livewire.js', $result['restored']);
        $this->assertStringContainsString('Restauradas', $result['summary']);

        $restoreCalls = $this->restoreCalls($calls);
        $this->assertCount(1, $restoreCalls);
        $this->assertContains('public/vendor/livewire/livewire.js', $restoreCalls[0]);
    }

    /** @test */
    public function prepare_returns_early_when_no_candidates(): void
    {
        $guard = new DeployGitPullWorktreeGuard(function (array $args, int $timeout): Process {
            return $this->successfulGit('');
        });

        $result = $guard->prepare('origin', 'main', []);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['restored']);
        $this->assertSame('Sin rutas que limpiar antes del pull.', $result['summary']);
    }

    /** @test */
    public function prepare_logs_warning_when_tracked_restore_fails_and_stays_ok(): void
    {
        $warnings = $this->captureRestoreWarnings();
        $tracked = "public/vendor/livewire/livewire.js\n";

        $guard = new DeployGitPullWorktreeGuard(function (array $args, int $timeout) use ($tracked): Process {
            if ($args[0] === 'restore') {
                throw new RuntimeException('pathspec did not match');
            }

            if ($args[0] === 'diff' || $args[0] === 'ls-files') {
                return $this->successfulGit($tracked);
            }

            return $this->successfulGit('');
        });

        $result = $guard->prepare('origin', 'main', []);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['restored']);
        $this->assertCount(1, $warnings);
        $this->assertSame('deploy.git_restore_failed', $warnings[0]->message);
        $this->assertSame('public/vendor/livewire/livewire.js', $warnings[0]->context['path'] ?? null);
    }

    /** @test */
    public function prepare_skips_restore_when_ls_files_throws_without_pathspec_warning(): void
    {
        $warnings = $this->captureRestoreWarnings();
        $calls = [];

        $guard = new DeployGitPullWorktreeGuard(function (array $args, int $timeout) use (&$calls): Process {
            $calls[] = $args;

            if ($args[0] === 'ls-files') {
                throw new RuntimeException('ls-files failed');
            }

            if ($args[0] === 'diff') {
                return $this->successfulGit("public/vendor/livewire/livewire.js\n");
            }

            return $this->successfulGit('');
        });

        $result = $guard->prepare('origin', 'main', ['public/vendor/livewire/']);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['restored']);
        $this->assertSame([], $this->restoreCalls($calls));
        $this->assertCount(0, $warnings);
    }

    private function successfulGit(string $output): Process
    {
        $process = $this->createMock(Process::class);
        $process->method('isSuccessful')->willReturn(true);
        $process->method('getOutput')->willReturn($output);

        return $process;
    }

    /**
     * @param  list<array<int, string>>  $calls
     * @return list<array<int, string>>
     */
    private function restoreCalls(array $calls): array
    {
        return array_values(array_filter($calls, fn (array $args): bool => $args[0] === 'restore'));
    }

    /**
     * @return ArrayObject<int, MessageLogged>
     */
    private function captureRestoreWarnings(): ArrayObject
    {
        /** @var ArrayObject<int, MessageLogged> $warnings */
        $warnings = new ArrayObject;

        Log::listen(function (MessageLogged $event) use ($warnings): void {
            if ($event->message === 'deploy.git_restore_failed') {
                $warnings->append($event);
            }
        });

        return $warnings;
    }
}
