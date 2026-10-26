<?php

namespace App\Support\Database;

class DatabaseBackupSkipGuard
{
    /**
     * Omite el respaldo programado si ya existe un archivo reciente (p. ej. del orquestador de deploy).
     */
    public function shouldSkipScheduledBackup(): bool
    {
        $minutesSince = $this->minutesSinceLatestBackupFile();

        if ($minutesSince === null) {
            return false;
        }

        $dedupeMinutes = max(1, (int) config('centinela.backup.schedule_dedupe_minutes', 180));

        return $minutesSince < $dedupeMinutes;
    }

    public function minutesSinceLatestBackupFile(): ?int
    {
        $files = glob(storage_path('app/backups/backup_*')) ?: [];

        if ($files === []) {
            return null;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        $latest = $files[0];
        if (! is_file($latest)) {
            return null;
        }

        return (int) floor((time() - (int) filemtime($latest)) / 60);
    }
}
