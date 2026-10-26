<?php

namespace Tests\Feature;

use App\Console\Commands\CentinelaPruneScheduleLogsCommand;
use Tests\TestCase;

class CentinelaPruneScheduleLogsCommandTest extends TestCase
{
    private string $logsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logsDir = storage_path('logs');
        if (! is_dir($this->logsDir)) {
            mkdir($this->logsDir, 0755, true);
        }
    }

    public function test_rotates_oversized_active_log_and_deletes_stale_archives(): void
    {
        $basename = 'db-backup.log';
        $this->assertContains($basename, CentinelaPruneScheduleLogsCommand::SCHEDULE_LOG_BASENAMES);

        $active = $this->logsDir.DIRECTORY_SEPARATOR.$basename;
        file_put_contents($active, str_repeat('x', 2048));

        $staleArchive = $this->logsDir.DIRECTORY_SEPARATOR.'db-backup-20200101000000.log';
        file_put_contents($staleArchive, 'old');
        touch($staleArchive, time() - (86400 * 40));

        $freshArchive = $this->logsDir.DIRECTORY_SEPARATOR.'db-backup-'.now()->format('YmdHis').'.log';
        file_put_contents($freshArchive, 'fresh');

        $this->artisan('centinela:prune-schedule-logs', [
            '--days' => 14,
            '--max-bytes' => 1024,
        ])->assertExitCode(0);

        $this->assertFileExists($active);
        $this->assertSame(0, filesize($active));
        $this->assertFileDoesNotExist($staleArchive);
        $this->assertFileExists($freshArchive);

        foreach (glob($this->logsDir.DIRECTORY_SEPARATOR.'db-backup-*.log') ?: [] as $path) {
            unlink($path);
        }
        if (is_file($active)) {
            unlink($active);
        }
    }

    public function test_dry_run_does_not_rotate_or_delete(): void
    {
        $active = $this->logsDir.DIRECTORY_SEPARATOR.'audit-cleanup.log';
        file_put_contents($active, str_repeat('y', 4096));

        $staleArchive = $this->logsDir.DIRECTORY_SEPARATOR.'audit-cleanup-20190101000000.log';
        file_put_contents($staleArchive, 'old');
        touch($staleArchive, time() - (86400 * 60));

        $this->artisan('centinela:prune-schedule-logs', [
            '--days' => 14,
            '--max-bytes' => 1024,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertFileExists($active);
        $this->assertSame(4096, filesize($active));
        $this->assertFileExists($staleArchive);

        unlink($active);
        unlink($staleArchive);
    }

    public function test_does_not_touch_laravel_log(): void
    {
        $laravel = $this->logsDir.DIRECTORY_SEPARATOR.'laravel.log';
        $payload = str_repeat('z', 8192);
        file_put_contents($laravel, $payload);

        $this->artisan('centinela:prune-schedule-logs', [
            '--days' => 1,
            '--max-bytes' => 100,
        ])->assertExitCode(0);

        $this->assertFileExists($laravel);
        $this->assertSame(strlen($payload), filesize($laravel));

        unlink($laravel);
    }
}
