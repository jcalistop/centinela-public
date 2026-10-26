<?php

namespace App\Support\Cen;

use App\Jobs\SyncNationalEstablishmentsJob;
use App\Models\CenHieDependency;
use App\Models\User;
use App\Services\Cen\CenCenterSyncService;
use RuntimeException;

/**
 * Dispara sync nacional reutilizando las mismas reglas que CenCenterController (SDD-264).
 */
final class CenCenterNationalSyncDispatcher
{
    public function canSyncServicioSalud(User $user): bool
    {
        return $user->hasRole(['admin', 'control-gestion'])
            && (bool) config('centinela.national_establishments.enabled', false);
    }

    public function canSyncNationalFull(User $user): bool
    {
        return $user->hasRole('admin')
            && (bool) config('centinela.national_establishments.enabled', false);
    }

    public function resolveSsCodigo(User $user): ?string
    {
        if ($user->cen_hie_dependency_id === null) {
            return null;
        }

        $codigo = CenHieDependency::query()
            ->whereKey($user->cen_hie_dependency_id)
            ->value('codigo');

        if (! is_string($codigo) || $codigo === '') {
            return null;
        }

        return $codigo;
    }

    /**
     * @throws RuntimeException
     */
    public function dispatchServicioSalud(User $user): string
    {
        if (! $this->canSyncServicioSalud($user)) {
            throw new RuntimeException('No autorizado para sincronizar el catálogo del SS.');
        }

        $ssCodigo = $this->resolveSsCodigo($user);

        if ($ssCodigo === null) {
            throw new RuntimeException('Su usuario no tiene un Servicio de Salud asociado; no se puede encolar la sincronización.');
        }

        app(CenCenterSyncService::class)->ensureSyncNotAlreadyRunning($ssCodigo);
        SyncNationalEstablishmentsJob::dispatch($ssCodigo, $user->id);

        return "Sincronización del catálogo encolada para el SS {$ssCodigo}.";
    }

    /**
     * @throws RuntimeException
     */
    public function dispatchNationalFull(User $user): string
    {
        if (! $this->canSyncNationalFull($user)) {
            throw new RuntimeException('No autorizado para sincronización nacional completa.');
        }

        app(CenCenterSyncService::class)->ensureSyncNotAlreadyRunning(null);
        SyncNationalEstablishmentsJob::dispatch(null, $user->id);

        return 'Sincronización nacional completa del catálogo encolada.';
    }
}
