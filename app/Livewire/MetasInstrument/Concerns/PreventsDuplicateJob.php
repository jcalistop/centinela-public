<?php

namespace App\Livewire\MetasInstrument\Concerns;

use Illuminate\Support\Facades\Cache;

trait PreventsDuplicateJob
{
    protected function shouldDispatchJob(string $jobFingerprint, int $cooldownSeconds = 30): bool
    {
        $cacheKey = 'metas_job_pending_'.$jobFingerprint;

        if (Cache::has($cacheKey)) {
            return false;
        }

        Cache::put($cacheKey, true, now()->addSeconds($cooldownSeconds));

        return true;
    }
}
