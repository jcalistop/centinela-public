<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Cen\CenCenterSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncNationalEstablishmentsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly ?string $ssCodigo = null,
        public readonly ?int $triggeredByUserId = null,
    ) {}

    public function uniqueId(): string
    {
        return 'cen-national-sync:'.($this->ssCodigo ?? 'full');
    }

    public function handle(CenCenterSyncService $syncService): void
    {
        $user = $this->triggeredByUserId !== null
            ? User::query()->find($this->triggeredByUserId)
            : null;

        Log::info('[cen.sync-national] start', [
            'ss_codigo' => $this->ssCodigo,
            'triggered_by_user_id' => $this->triggeredByUserId,
        ]);

        try {
            $run = $syncService->sync($this->ssCodigo, $user);
        } catch (\RuntimeException $exception) {
            Log::info('[cen.sync-national] skipped (already running or lock held)', [
                'ss_codigo' => $this->ssCodigo,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        Log::info('[cen.sync-national] completed', [
            'run_id' => $run->id,
            'status' => $run->status,
            'created' => $run->created_count,
            'updated' => $run->updated_count,
            'soft_deleted' => $run->soft_deleted_count,
            'skipped' => $run->skipped_count,
            'warnings' => $run->warning_count,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('[cen.sync-national] failed', [
            'ss_codigo' => $this->ssCodigo,
            'triggered_by_user_id' => $this->triggeredByUserId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
