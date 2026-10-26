<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Finder\SplFileInfo;

class CentinelaCleanStorageTempCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'centinela:clean-storage-temp
                            {--hours= : Antigüedad mínima en horas para eliminar (por defecto desde config)}
                            {--dry-run : Listar archivos candidatos sin borrarlos}';

    /**
     * @var string
     */
    protected $description = 'Elimina archivos antiguos en storage/temp y storage/app/temp (temporales huérfanos)';

    public function handle(): int
    {
        $configuredHours = (int) config('centinela.storage_temp.max_age_hours', 48);
        $hoursOption = $this->option('hours');
        $maxAgeHours = $hoursOption !== null && $hoursOption !== ''
            ? (int) $hoursOption
            : $configuredHours;

        if ($maxAgeHours < 1) {
            $this->error('El valor de --hours debe ser un entero mayor o igual a 1.');

            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subHours($maxAgeHours);

        $this->info('Limpieza de temporales (storage/temp + storage/app/temp)');
        $this->line("  Umbral: archivos no modificados desde antes de {$cutoff->toDateTimeString()}");
        $this->line('  Modo: '.($dryRun ? 'simulación (--dry-run)' : 'eliminación real'));
        $this->newLine();

        $totals = [
            'deleted' => 0,
            'skipped_recent' => 0,
            'skipped_locks' => 0,
            'bytes_freed' => 0,
            'missing_dirs' => 0,
        ];

        foreach ($this->targetDirectories() as $label => $directory) {
            $result = $this->cleanDirectory($label, $directory, $cutoff, $dryRun);
            foreach (['deleted', 'skipped_recent', 'skipped_locks', 'bytes_freed', 'missing_dirs'] as $key) {
                $totals[$key] += $result[$key];
            }
        }

        if ($totals['missing_dirs'] === count($this->targetDirectories())) {
            $this->info('No existen directorios temporales configurados; nada que limpiar.');
        }

        $this->info("Archivos eliminados (o candidatos en dry-run): {$totals['deleted']}");
        $this->line("Omitidos (recientes): {$totals['skipped_recent']}");
        $this->line("Omitidos (locks): {$totals['skipped_locks']}");
        $this->line('Espacio liberado (aprox.): '.number_format($totals['bytes_freed']).' bytes');

        Log::info('centinela:clean-storage-temp ejecutado', [
            'deleted' => $totals['deleted'],
            'skipped_recent' => $totals['skipped_recent'],
            'skipped_locks' => $totals['skipped_locks'],
            'bytes_freed' => $totals['bytes_freed'],
            'max_age_hours' => $maxAgeHours,
            'dry_run' => $dryRun,
            'directories' => array_keys($this->targetDirectories()),
        ]);

        return Command::SUCCESS;
    }

    /**
     * Paths temporales distintos (no unificados): exports vs store('temp').
     *
     * @return array<string, string>
     */
    private function targetDirectories(): array
    {
        return [
            'storage/temp' => storage_path('temp'),
            'storage/app/temp' => storage_path('app/temp'),
        ];
    }

    /**
     * @return array{deleted: int, skipped_recent: int, skipped_locks: int, bytes_freed: int, missing_dirs: int}
     */
    private function cleanDirectory(string $label, string $directory, Carbon $cutoff, bool $dryRun): array
    {
        $result = [
            'deleted' => 0,
            'skipped_recent' => 0,
            'skipped_locks' => 0,
            'bytes_freed' => 0,
            'missing_dirs' => 0,
        ];

        if (! is_dir($directory)) {
            $this->line("[{$label}] no existe; se omite.");
            $result['missing_dirs'] = 1;

            return $result;
        }

        $this->line("[{$label}]");

        /** @var list<SplFileInfo> $files */
        $files = File::files($directory);

        foreach ($files as $file) {
            $path = $file->getPathname();
            $basename = $file->getBasename();

            if ($this->isConcurrencyLockFile($basename)) {
                $result['skipped_locks']++;

                continue;
            }

            $mtime = Carbon::createFromTimestamp((int) $file->getMTime());
            if ($mtime->greaterThanOrEqualTo($cutoff)) {
                $result['skipped_recent']++;

                continue;
            }

            $size = (int) $file->getSize();

            if ($dryRun) {
                $this->line("  [dry-run] eliminaría: {$basename} (".number_format($size).' bytes, mtime '.$mtime->toDateTimeString().')');
                $result['deleted']++;
                $result['bytes_freed'] += $size;

                continue;
            }

            if (unlink($path)) {
                $result['deleted']++;
                $result['bytes_freed'] += $size;
            } else {
                $this->warn("  No se pudo eliminar: {$path}");
                Log::warning('centinela:clean-storage-temp no pudo eliminar archivo', [
                    'path' => $path,
                    'directory_label' => $label,
                ]);
            }
        }

        return $result;
    }

    private function isConcurrencyLockFile(string $basename): bool
    {
        if (str_ends_with($basename, '.lock')) {
            return true;
        }

        return str_starts_with($basename, 'lock_');
    }
}
