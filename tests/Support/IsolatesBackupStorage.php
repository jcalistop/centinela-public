<?php

namespace Tests\Support;

use Illuminate\Support\Facades\File;

trait IsolatesBackupStorage
{
    private string $isolatedBackupStorage;

    protected function isolateBackupStorage(): void
    {
        $this->isolatedBackupStorage = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'centinela-backup-tests-'.uniqid('', true);

        mkdir($this->isolatedBackupStorage.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'backups', 0755, true);

        $this->app->useStoragePath($this->isolatedBackupStorage);
    }

    protected function cleanupIsolatedBackupStorage(): void
    {
        if (! isset($this->isolatedBackupStorage) || ! is_dir($this->isolatedBackupStorage)) {
            return;
        }

        File::deleteDirectory($this->isolatedBackupStorage);
    }
}
