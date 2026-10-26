<?php

namespace App\Console\Commands;

use App\Jobs\SyncNationalEstablishmentsJob;
use App\Services\Cen\CenCenterSyncService;
use Illuminate\Console\Command;

class SyncNationalEstablishmentsCommand extends Command
{
    protected $signature = 'cen:sync-establishments
                            {--ss= : Código del Servicio de Salud (filtro CKAN)}
                            {--sync : Ejecutar en este proceso (sin cola); útil para depuración}';

    protected $description = 'Sincroniza el catálogo de establecimientos desde datos.gob.cl (CKAN); por defecto encola un job';

    public function handle(CenCenterSyncService $syncService): int
    {
        $ss = $this->option('ss');
        $ssCodigo = is_string($ss) && $ss !== '' ? $ss : null;

        if (! $this->option('sync')) {
            SyncNationalEstablishmentsJob::dispatch($ssCodigo, null);
            $scope = $ssCodigo ?? 'completo';
            $this->info("Sincronización encolada (alcance: {$scope}). Ejecute un worker: php artisan queue:work");

            return self::SUCCESS;
        }

        $run = $syncService->sync($ssCodigo, null);

        $this->info(sprintf(
            'Sync %s: creados=%d actualizados=%d soft_deleted=%d omitidos=%d warnings=%d',
            $run->status,
            $run->created_count,
            $run->updated_count,
            $run->soft_deleted_count,
            $run->skipped_count,
            $run->warning_count,
        ));

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
