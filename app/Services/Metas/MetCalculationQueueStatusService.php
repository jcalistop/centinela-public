<?php

declare(strict_types=1);

namespace App\Services\Metas;

use App\Jobs\MetCalculateIndicatorScopeJob;
use App\Jobs\MetCalculateResultsJob;
use App\Support\Health\HealthStatusService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de trabajos de cálculo Metas en cola.
 *
 * Reutiliza el enfoque de {@see HealthStatusService::workerStatus()}
 * sobre la tabla `jobs`, filtrando por clases de cálculo y alcance SS/instrumento.
 */
class MetCalculationQueueStatusService
{
    /** @var list<class-string> */
    private const CALCULATION_JOB_CLASSES = [
        MetCalculateResultsJob::class,
        MetCalculateIndicatorScopeJob::class,
    ];

    /**
     * @return array{
     *     pending_jobs: int,
     *     estimated_minutes: int,
     *     message: string|null
     * }
     */
    public function statusForScope(int $dependencyId, ?int $indicatorTypeId = null): array
    {
        if (! Schema::hasTable('jobs')) {
            return $this->emptyStatus();
        }

        $rows = DB::table('jobs')
            ->select(['payload', 'available_at'])
            ->where(function ($query): void {
                foreach (self::CALCULATION_JOB_CLASSES as $jobClass) {
                    $query->orWhere('payload', 'like', '%'.class_basename($jobClass).'%');
                }
            })
            ->orderBy('available_at')
            ->get();

        $matchingJobs = $rows->filter(
            fn (object $row): bool => $this->matchesScope((string) $row->payload, $dependencyId, $indicatorTypeId)
        );

        $pendingJobs = $matchingJobs->count();

        if ($pendingJobs === 0) {
            return $this->emptyStatus();
        }

        $estimatedMinutes = $this->estimateMinutesUntilProcessed($matchingJobs, $pendingJobs);

        return [
            'pending_jobs' => $pendingJobs,
            'estimated_minutes' => $estimatedMinutes,
            'message' => $this->formatMessage($pendingJobs, $estimatedMinutes),
        ];
    }

    /**
     * @return array{pending_jobs: int, estimated_minutes: int, message: null}
     */
    private function emptyStatus(): array
    {
        return [
            'pending_jobs' => 0,
            'estimated_minutes' => 0,
            'message' => null,
        ];
    }

    private function matchesScope(string $payload, int $dependencyId, ?int $instrumentTypeId): bool
    {
        if (! $this->payloadContainsIntProperty($payload, 'dependencyId', $dependencyId)) {
            return false;
        }

        if ($instrumentTypeId === null) {
            return true;
        }

        return $this->payloadContainsIntProperty($payload, 'indicatorTypeId', $instrumentTypeId);
    }

    private function payloadContainsIntProperty(string $payload, string $property, int $value): bool
    {
        return str_contains($payload, $property.'\";i:'.$value)
            || str_contains($payload, '"'.$property.'";i:'.$value)
            || str_contains($payload, $property.'";i:'.$value);
    }

    /**
     * @param  Collection<int, object{payload: string, available_at: int|string|null}>  $matchingJobs
     */
    private function estimateMinutesUntilProcessed(Collection $matchingJobs, int $pendingJobs): int
    {
        $minutesPerJob = max(1, (int) config('centinela.metas.calculation_queue_minutes_per_job', 2));

        $oldestAvailableAt = $matchingJobs
            ->pluck('available_at')
            ->filter(fn ($timestamp) => $timestamp !== null && $timestamp !== '')
            ->map(fn ($timestamp) => (int) $timestamp)
            ->min();

        $readyInMinutes = 0;

        if ($oldestAvailableAt !== null) {
            $availableAt = Carbon::createFromTimestamp($oldestAvailableAt);

            if ($availableAt->isFuture()) {
                $readyInMinutes = now()->diffInMinutes($availableAt);
            }
        }

        $processingMinutes = (int) ceil($pendingJobs * $minutesPerJob);

        return max(1, $readyInMinutes + $processingMinutes);
    }

    private function formatMessage(int $pendingJobs, int $estimatedMinutes): string
    {
        $processLabel = $pendingJobs === 1 ? 'proceso' : 'procesos';
        $minuteLabel = $estimatedMinutes === 1 ? 'minuto' : 'minutos';

        return "Existen {$pendingJobs} {$processLabel} de cálculo en cola que se ejecutarán en los próximos {$estimatedMinutes} {$minuteLabel}.";
    }
}
