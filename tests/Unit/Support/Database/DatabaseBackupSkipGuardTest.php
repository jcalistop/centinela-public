<?php

namespace Tests\Unit\Support\Database;

use App\Support\Database\DatabaseBackupSkipGuard;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\IsolatesBackupStorage;
use Tests\TestCase;

class DatabaseBackupSkipGuardTest extends TestCase
{
    use IsolatesBackupStorage;

    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolateBackupStorage();

        $this->backupDir = storage_path('app/backups');
        if (! is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        config()->set('centinela.backup.schedule_dedupe_minutes', 180);
    }

    protected function tearDown(): void
    {
        $this->cleanupIsolatedBackupStorage();

        parent::tearDown();
    }

    public function test_should_not_skip_when_backup_is_older_than_dedupe_window(): void
    {
        $path = $this->backupDir.'/backup_test_old.zip';
        touch($path, time() - 7200);

        config()->set('centinela.backup.schedule_dedupe_minutes', 60);

        $guard = new DatabaseBackupSkipGuard;

        $this->assertFalse($guard->shouldSkipScheduledBackup());
        $this->assertGreaterThanOrEqual(60, $guard->minutesSinceLatestBackupFile());
    }

    public function test_should_skip_when_recent_backup_exists(): void
    {
        $path = $this->backupDir.'/backup_test_recent.zip';
        touch($path);

        $guard = new DatabaseBackupSkipGuard;

        $this->assertTrue($guard->shouldSkipScheduledBackup());
        $this->assertSame(0, $guard->minutesSinceLatestBackupFile());
    }

    public function test_db_backup_command_skips_when_recent_backup_exists(): void
    {
        touch($this->backupDir.'/backup_test_recent.zip');

        $exit = Artisan::call('db:backup', ['--skip-if-recent' => true]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString(
            'Respaldo programado omitido',
            Artisan::output()
        );
    }
}
