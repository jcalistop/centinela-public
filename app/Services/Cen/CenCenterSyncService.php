<?php

namespace App\Services\Cen;

use App\Enums\Cen\CenCenterOrigen;
use App\Enums\Cen\CenCenterSyncRunChangeAction;
use App\Enums\Cen\CentroEstadoFuncionamiento;
use App\Models\CenAddress;
use App\Models\CenCenter;
use App\Models\CenCenterSyncRun;
use App\Models\CenCenterSyncRunChange;
use App\Models\CenCenterSyncRunIssue;
use App\Models\CenHieDependency;
use App\Models\User;
use App\Support\Cen\CenServicioSaludCodigo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CenCenterSyncService
{
    private const SYNC_LOCK_SECONDS = 1800;

    private const STALE_RUNNING_HOURS = 3;

    public function __construct(
        private readonly NationalEstablishmentCatalogClient $client,
        private readonly CenCenterNationalCatalogMapper $mapper,
        private readonly CenCenterSyncAttributeDiff $attributeDiff = new CenCenterSyncAttributeDiff,
    ) {}

    public function syncLockKey(?string $ssCodigo): string
    {
        return 'cen-national-sync:'.($ssCodigo ?? 'full');
    }

    /**
     * @throws \RuntimeException si ya hay una corrida activa para el mismo alcance
     */
    public function ensureSyncNotAlreadyRunning(?string $ssCodigo): void
    {
        $this->releaseStaleRunningRuns($ssCodigo);

        if ($this->hasActiveSyncRun($ssCodigo)) {
            throw new \RuntimeException($this->syncAlreadyRunningMessage($ssCodigo));
        }
    }

    public function hasActiveSyncRun(?string $ssCodigo): bool
    {
        return $this->activeSyncRunQuery($ssCodigo)->exists();
    }

    public function sync(?string $ssCodigo = null, ?User $triggeredBy = null): CenCenterSyncRun
    {
        if (! $this->client->isEnabled()) {
            throw new \RuntimeException('La sincronización de establecimientos está deshabilitada en settings.');
        }

        $lock = Cache::lock($this->syncLockKey($ssCodigo), self::SYNC_LOCK_SECONDS);

        try {
            if (! $lock->get()) {
                throw new \RuntimeException($this->syncAlreadyRunningMessage($ssCodigo));
            }

            $this->ensureSyncNotAlreadyRunning($ssCodigo);

            return $this->performSync($ssCodigo, $triggeredBy);
        } finally {
            $lock->release();
        }
    }

    private function performSync(?string $ssCodigo, ?User $triggeredBy): CenCenterSyncRun
    {
        app(CenCatalogGlosaEquivalenceService::class)->forgetCache();

        $run = CenCenterSyncRun::query()->create([
            'scope' => $ssCodigo ? 'ss' : 'full',
            'ss_codigo' => $ssCodigo,
            'triggered_by_user_id' => $triggeredBy?->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $seenCodigos = [];

        try {
            foreach ($this->client->fetchRecords($ssCodigo) as $record) {
                $mapped = $this->mapper->map($record);

                if ($mapped->skipped) {
                    $run->increment('skipped_count');
                    $this->logChange(
                        $run,
                        $mapped->codigo !== '' ? $mapped->codigo : null,
                        CenCenterSyncRunChangeAction::Skipped,
                        $mapped->skipReason ?? 'skipped',
                    );
                    $this->logIssue($run, $mapped->codigo ?: null, 'skipped', $mapped->skipReason ?? 'skipped', []);

                    continue;
                }

                $seenCodigos[] = $mapped->codigo;

                $center = CenCenter::withTrashed()
                    ->with('cenAddress')
                    ->where('codigo', $mapped->codigo)
                    ->first();
                $wasTrashed = $center?->trashed() ?? false;

                if ($center && $center->origen === CenCenterOrigen::Manual) {
                    $run->increment('skipped_count');
                    $this->logChange(
                        $run,
                        $mapped->codigo,
                        CenCenterSyncRunChangeAction::Skipped,
                        'manual_origen',
                        $this->resolveCenterDisplayName($center),
                    );
                    $this->logIssue($run, $mapped->codigo, 'skipped', 'manual_origen', []);

                    continue;
                }

                $isClosed = CentroEstadoFuncionamiento::isClosedValue(
                    $mapped->centerAttributes['estado_funcionamiento'] ?? null
                );

                DB::transaction(function () use ($mapped, &$center, $run, $wasTrashed, $isClosed): void {
                    if ($center) {
                        $updateDiff = $this->attributeDiff->between(
                            $center,
                            $mapped->centerAttributes,
                            $mapped->addressAttributes,
                        );

                        if ($wasTrashed && ! $isClosed) {
                            $center->restore();
                            $run->increment('restored_count');
                            $this->logChange(
                                $run,
                                $mapped->codigo,
                                CenCenterSyncRunChangeAction::Restored,
                                null,
                                $this->resolveCenterDisplayName($center, $mapped->centerAttributes),
                            );
                        }
                        $center->update($mapped->centerAttributes);
                        $run->increment('updated_count');
                        $this->logChange(
                            $run,
                            $mapped->codigo,
                            CenCenterSyncRunChangeAction::Updated,
                            $this->summarizeAttributeDiff($updateDiff),
                            $this->resolveCenterDisplayName($center, $mapped->centerAttributes),
                            $updateDiff === [] ? null : ['changes' => $updateDiff],
                        );
                    } else {
                        $center = CenCenter::query()->create($mapped->centerAttributes);
                        $run->increment('created_count');
                        $this->logChange(
                            $run,
                            $mapped->codigo,
                            CenCenterSyncRunChangeAction::Created,
                            null,
                            $this->resolveCenterDisplayName($center),
                        );
                    }

                    if ($mapped->addressAttributes !== null) {
                        CenAddress::updateOrCreate(
                            ['cen_center_id' => $center->id],
                            $mapped->addressAttributes
                        );
                    }

                    foreach ($mapped->warnings as $warning) {
                        $run->increment('warning_count');
                        $this->logIssue(
                            $run,
                            $mapped->codigo,
                            $warning['severity'],
                            $warning['code'],
                            $this->warningContext($warning, $mapped->codigo, $mapped->centerAttributes),
                        );
                    }

                    if ($isClosed && ! $center->trashed()) {
                        $this->logChange(
                            $run,
                            $mapped->codigo,
                            CenCenterSyncRunChangeAction::SoftDeleted,
                            'closed_establishment',
                            $this->resolveCenterDisplayName($center, $mapped->centerAttributes),
                        );
                        $center->delete();
                        $run->increment('soft_deleted_count');
                        $this->logIssue(
                            $run,
                            $mapped->codigo,
                            'info',
                            'closed_establishment_soft_deleted',
                            [
                                'message' => 'Establecimiento con estado de cierre; baja lógica aplicada (origen=api).',
                                'estado_funcionamiento' => $mapped->centerAttributes['estado_funcionamiento'] ?? null,
                            ],
                        );
                    }
                });
            }

            $this->softDeleteMissingApiCenters($ssCodigo, $seenCodigos, $run);

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);

            $this->logIssue($run, null, 'error', 'sync_failed', ['message' => $e->getMessage()]);

            throw $e;
        }

        return $run->fresh() ?? $run;
    }

    public function mergeManualToApi(CenCenter $manualCenter, string $nationalCodigo, User $admin): CenCenter
    {
        if ($manualCenter->origen !== CenCenterOrigen::Manual) {
            throw new \InvalidArgumentException('Solo registros origen=manual pueden fusionarse.');
        }

        $existingApi = CenCenter::query()->where('codigo', $nationalCodigo)->where('origen', CenCenterOrigen::Api)->first();

        if ($existingApi !== null) {
            throw new \InvalidArgumentException('Ya existe un establecimiento API con ese código.');
        }

        $manualCenter->update([
            'codigo' => $nationalCodigo,
            'origen' => CenCenterOrigen::Api,
        ]);

        $ssCodigo = CenHieDependency::query()->whereKey($manualCenter->cen_hie_dependency_id)->value('codigo');
        $this->sync(is_string($ssCodigo) ? $ssCodigo : null, $admin);

        return $manualCenter->fresh() ?? $manualCenter;
    }

    /**
     * @param  list<string>  $seenCodigos
     */
    private function softDeleteMissingApiCenters(?string $ssCodigo, array $seenCodigos, CenCenterSyncRun $run): void
    {
        $query = CenCenter::query()
            ->where('origen', CenCenterOrigen::Api->value);

        if ($ssCodigo !== null && $ssCodigo !== '') {
            $dependency = CenServicioSaludCodigo::findDependency($ssCodigo);

            if ($dependency === null) {
                return;
            }

            $query->where('cen_hie_dependency_id', $dependency->id);
        }

        if ($seenCodigos !== []) {
            $query->whereNotIn('codigo', $seenCodigos);
        }

        $deleted = 0;

        (clone $query)
            ->select(['id', 'codigo', 'nombre_corto', 'nombre_oficial'])
            ->orderBy('id')
            ->chunkById(200, function ($centers) use ($run, &$deleted): void {
                foreach ($centers as $center) {
                    if (! $center instanceof CenCenter) {
                        continue;
                    }

                    $this->logChange(
                        $run,
                        $center->codigo,
                        CenCenterSyncRunChangeAction::SoftDeleted,
                        'missing_from_catalog',
                        $this->resolveCenterDisplayName($center),
                    );
                }

                $ids = $centers->pluck('id')->all();
                $deleted += CenCenter::query()->whereIn('id', $ids)->delete();
            });

        $run->increment('soft_deleted_count', $deleted);
    }

    private function releaseStaleRunningRuns(?string $ssCodigo): void
    {
        $this->activeSyncRunQuery($ssCodigo)
            ->where('started_at', '<', now()->subHours(self::STALE_RUNNING_HOURS))
            ->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);
    }

    /**
     * @return Builder<CenCenterSyncRun>
     */
    private function activeSyncRunQuery(?string $ssCodigo)
    {
        $query = CenCenterSyncRun::query()->where('status', 'running');

        if ($ssCodigo !== null && $ssCodigo !== '') {
            return $query->where('ss_codigo', $ssCodigo);
        }

        return $query->where('scope', 'full');
    }

    private function syncAlreadyRunningMessage(?string $ssCodigo): string
    {
        if ($ssCodigo !== null && $ssCodigo !== '') {
            return "Ya hay una sincronización en curso para el Servicio de Salud {$ssCodigo}. Revise la pestaña Historial sincronización.";
        }

        return 'Ya hay una sincronización nacional en curso. Revise la pestaña Historial sincronización.';
    }

    /**
     * @param  array{code: string, message: string, severity: string, context?: array<string, mixed>}  $warning
     * @param  array<string, mixed>  $centerAttributes
     * @return array<string, mixed>
     */
    private function warningContext(array $warning, ?string $codigo = null, array $centerAttributes = []): array
    {
        $context = $warning['context'] ?? [];
        $context['message'] = $warning['message'];

        if ($codigo !== null && $codigo !== '') {
            $context['establecimiento_codigo'] = $codigo;
            $nombre = $centerAttributes['nombre_oficial'] ?? $centerAttributes['nombre_corto'] ?? null;
            if (is_string($nombre) && $nombre !== '') {
                $context['establecimiento_nombre'] = $nombre;
            }
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logIssue(
        CenCenterSyncRun $run,
        ?string $codigo,
        string $severity,
        string $code,
        array $context,
    ): void {
        CenCenterSyncRunIssue::query()->create([
            'cen_center_sync_run_id' => $run->id,
            'establecimiento_codigo' => $codigo,
            'severity' => $severity,
            'code' => $code,
            'message' => $context['message'] ?? $code,
            'context' => $context === [] ? null : $context,
        ]);
    }

    /**
     * @param  array<string, array{old: ?string, new: ?string}>  $diff
     */
    private function summarizeAttributeDiff(array $diff): ?string
    {
        if ($diff === []) {
            return 'sin_cambios_datos';
        }

        $summary = implode(', ', array_keys($diff));

        return strlen($summary) > 64 ? substr($summary, 0, 61).'…' : $summary;
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function logChange(
        CenCenterSyncRun $run,
        ?string $codigo,
        CenCenterSyncRunChangeAction $action,
        ?string $detail = null,
        ?string $nombreDisplay = null,
        ?array $context = null,
    ): void {
        if (! Schema::hasTable('cen_center_sync_run_changes')) {
            return;
        }

        $payload = [
            'cen_center_sync_run_id' => $run->id,
            'establecimiento_codigo' => $codigo,
            'nombre_display' => $nombreDisplay,
            'action' => $action,
            'detail' => $detail,
        ];

        if ($context !== null && Schema::hasColumn('cen_center_sync_run_changes', 'context')) {
            $payload['context'] = $context;
        }

        CenCenterSyncRunChange::query()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveCenterDisplayName(CenCenter $center, array $attributes = []): ?string
    {
        $nombre = $attributes['nombre_oficial'] ?? $attributes['nombre_corto'] ?? null;

        if (is_string($nombre) && $nombre !== '') {
            return $nombre;
        }

        $fromCenter = $center->nombre_oficial ?? $center->nombre_corto;

        return is_string($fromCenter) && $fromCenter !== '' ? $fromCenter : null;
    }
}
