<?php

declare(strict_types=1);

namespace App\Support\Deploy;

use App\Models\DeployExecution;
use Illuminate\Support\Facades\Log;

class DeployOrphanReconciler
{
    public function __construct(
        private readonly DeployAdminOpsNotifier $adminOpsNotifier,
    ) {}

    /**
     * Cierra filas running/queued sin finished_at cuya edad supera el TTL.
     *
     * @return int Cantidad de filas cerradas
     */
    public function reconcile(): int
    {
        $ttl = max(1, (int) config('centinela.deploy.orphan_ttl_seconds', 1800));
        $cutoff = now()->subSeconds($ttl);
        $closed = 0;

        $candidates = DeployExecution::query()
            ->whereIn('status', ['running', 'queued'])
            ->whereNull('finished_at')
            ->orderBy('id')
            ->get();

        foreach ($candidates as $execution) {
            $ageReference = $execution->started_at ?? $execution->created_at;
            if ($ageReference === null || $ageReference->greaterThan($cutoff)) {
                continue;
            }

            if ($this->closeOrphan($execution, $ttl)) {
                $closed++;
            }
        }

        return $closed;
    }

    public function closeOrphan(DeployExecution $execution, ?int $ttlSeconds = null): bool
    {
        $status = (string) $execution->status;
        if (! in_array($status, ['running', 'queued'], true) || $execution->finished_at !== null) {
            return false;
        }

        $ttl = $ttlSeconds ?? max(1, (int) config('centinela.deploy.orphan_ttl_seconds', 1800));
        $summary = sprintf(
            'Ejecución cerrada por watchdog: sin finished_at tras %d s (estado previo: %s).',
            $ttl,
            $status
        );

        $execution->status = 'failed';
        $execution->summary = $summary;
        $execution->finished_at = now();
        $execution->started_at = $execution->started_at ?? $execution->created_at ?? now();
        $execution->meta = array_merge(is_array($execution->meta) ? $execution->meta : [], [
            'reason' => 'orphan_timeout',
            'orphan_ttl_seconds' => $ttl,
            'orphan_previous_status' => $status,
        ]);
        $execution->save();

        Log::warning('centinela.deploy.orphan_timeout', [
            'execution_id' => $execution->id,
            'previous_status' => $status,
            'ttl_seconds' => $ttl,
        ]);

        $this->adminOpsNotifier->notifyIfApplicable($execution->fresh() ?? $execution);

        return true;
    }
}
