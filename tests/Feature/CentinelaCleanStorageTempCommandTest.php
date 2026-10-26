<?php

namespace Tests\Feature;

use Tests\TestCase;

class CentinelaCleanStorageTempCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([storage_path('temp'), storage_path('app/temp')] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    public function test_removes_stale_files_and_keeps_recent(): void
    {
        $old = storage_path('temp/clean_temp_test_old_'.uniqid('', true).'.xlsx');
        file_put_contents($old, 'old');
        touch($old, time() - (3600 * 100));

        $recent = storage_path('temp/clean_temp_test_recent_'.uniqid('', true).'.xlsx');
        file_put_contents($recent, 'recent');

        $this->artisan('centinela:clean-storage-temp', ['--hours' => 48])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);

        unlink($recent);
    }

    public function test_removes_stale_files_under_storage_app_temp(): void
    {
        $old = storage_path('app/temp/clean_app_temp_old_'.uniqid('', true).'.xlsx');
        file_put_contents($old, 'old');
        touch($old, time() - (3600 * 100));

        $recent = storage_path('app/temp/clean_app_temp_recent_'.uniqid('', true).'.xlsx');
        file_put_contents($recent, 'recent');

        $this->artisan('centinela:clean-storage-temp', ['--hours' => 48])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);

        unlink($recent);
    }

    public function test_does_not_remove_lock_files_even_when_stale(): void
    {
        $lock = storage_path('temp/clean_temp_test_lock_'.uniqid('', true).'.lock');
        file_put_contents($lock, '1');
        touch($lock, time() - (3600 * 200));

        $this->artisan('centinela:clean-storage-temp', ['--hours' => 48])
            ->assertExitCode(0);

        $this->assertFileExists($lock);

        unlink($lock);
    }

    public function test_dry_run_does_not_delete_files(): void
    {
        $old = storage_path('temp/clean_temp_test_dry_'.uniqid('', true).'.xlsx');
        file_put_contents($old, 'x');
        touch($old, time() - (3600 * 100));

        $appOld = storage_path('app/temp/clean_app_temp_dry_'.uniqid('', true).'.xlsx');
        file_put_contents($appOld, 'x');
        touch($appOld, time() - (3600 * 100));

        $this->artisan('centinela:clean-storage-temp', [
            '--hours' => 48,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertFileExists($old);
        $this->assertFileExists($appOld);

        unlink($old);
        unlink($appOld);
    }

    public function test_does_not_touch_permanent_upload_paths(): void
    {
        $privateDir = storage_path('app/private');
        if (! is_dir($privateDir)) {
            mkdir($privateDir, 0755, true);
        }

        $sentinel = $privateDir.DIRECTORY_SEPARATOR.'clean_temp_should_keep_'.uniqid('', true).'.bin';
        file_put_contents($sentinel, 'keep');
        touch($sentinel, time() - (3600 * 200));

        $this->artisan('centinela:clean-storage-temp', ['--hours' => 1])
            ->assertExitCode(0);

        $this->assertFileExists($sentinel);

        unlink($sentinel);
    }
}
