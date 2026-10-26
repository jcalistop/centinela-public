<?php

namespace App\Jobs;

use App\Services\Metas\MetAutoevaluacionCalculationRunner;
use App\Services\Metas\MetRecalculationScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetCalculateIndicatorScopeJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 120;

    public function __construct(
        public MetRecalculationScope $scope,
    ) {
        $this->uniqueFor = max(
            120,
            (int) config('centinela.metas.recalculation_debounce_seconds', 90) + 30,
        );
    }

    public function uniqueId(): string
    {
        return $this->scope->fingerprint();
    }

    public function handle(MetAutoevaluacionCalculationRunner $runner): void
    {
        $result = $runner->runForIndicatorAtCenter(
            $this->scope->yearId,
            $this->scope->dependencyId,
            $this->scope->centerId,
            $this->scope->metIndicatorId,
            $this->scope->measurementUnitId,
        );

        if ($result['failed'] > 0) {
            Log::warning('[metas.calculate.scope] completed with failures', [
                'scope' => $this->scope->fingerprint(),
                'processed' => $result['processed'],
                'failed' => $result['failed'],
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[metas.calculate.scope] job failed', [
            'scope' => $this->scope->fingerprint(),
            'exception' => $exception->getMessage(),
        ]);
    }
}
