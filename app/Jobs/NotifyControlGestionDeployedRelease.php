<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Releases\ReleaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyControlGestionDeployedRelease implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public int $executionId,
        public bool $isTest = false,
        public bool $forceResend = false,
    ) {}

    public function handle(ReleaseNotificationService $service): void
    {
        $service->executeNotification($this->executionId, $this->isTest, $this->forceResend);
    }
}
