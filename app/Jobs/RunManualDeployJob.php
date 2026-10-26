<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DeployExecution;
use App\Models\User;
use App\Support\Deploy\DeployAdminOpsNotifier;
use App\Support\Deploy\DeployOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunManualDeployJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public int $executionId,
        public int $actorUserId,
    ) {
        $this->onQueue('releases');
    }

    public function handle(DeployOrchestrator $deploy): void
    {
        $execution = DeployExecution::query()->find($this->executionId);
        if (! $execution instanceof DeployExecution) {
            Log::warning('RunManualDeployJob: execution not found', [
                'execution_id' => $this->executionId,
                'user_id' => $this->actorUserId,
            ]);

            return;
        }

        $jobId = null;
        try {
            if ($this->job !== null) {
                $jobId = $this->job->getJobId();
            }
        } catch (\Throwable) {
            $jobId = null;
        }

        if ($jobId !== null && $jobId !== '') {
            $meta = is_array($execution->meta) ? $execution->meta : [];
            $meta['job_id'] = (string) $jobId;
            $meta['queue'] = $meta['queue'] ?? 'releases';
            $execution->meta = $meta;
            $execution->save();
        }

        $actor = User::query()->find($this->actorUserId);
        if (! $actor instanceof User) {
            Log::warning('RunManualDeployJob: actor not found', [
                'execution_id' => $this->executionId,
                'user_id' => $this->actorUserId,
            ]);
            $deploy->failQueuedExecution($execution, 'Actor no encontrado al procesar job de deploy.', [
                'reason' => 'actor_missing',
            ]);

            return;
        }

        $deploy->runManual($actor, $this->executionId);
    }

    public function failed(?\Throwable $exception = null): void
    {
        $execution = DeployExecution::query()->find($this->executionId);
        if (! $execution instanceof DeployExecution) {
            Log::warning('RunManualDeployJob::failed: execution not found', [
                'execution_id' => $this->executionId,
                'user_id' => $this->actorUserId,
                'error' => $exception?->getMessage(),
            ]);

            return;
        }

        $status = (string) $execution->status;
        if (in_array($status, DeployAdminOpsNotifier::TERMINAL_STATUSES, true) && $execution->finished_at !== null) {
            return;
        }

        $terminalStatus = $status === 'queued' ? 'failed_precondition' : 'failed';
        $summary = 'Job de deploy falló en cola'.($exception !== null ? ': '.$exception->getMessage() : '.');

        $execution->status = $terminalStatus;
        $execution->summary = $summary;
        $execution->finished_at = now();
        $execution->started_at = $execution->started_at ?? now();
        $execution->meta = array_merge(is_array($execution->meta) ? $execution->meta : [], [
            'reason' => 'job_failed',
            'job_failed_message' => $exception?->getMessage(),
        ]);
        $execution->save();

        Log::error('centinela.deploy.job_failed', [
            'execution_id' => $execution->id,
            'user_id' => $this->actorUserId,
            'previous_status' => $status,
            'status' => $terminalStatus,
            'error' => $exception?->getMessage(),
        ]);

        app(DeployAdminOpsNotifier::class)->notifyIfApplicable($execution->fresh() ?? $execution);
    }
}
