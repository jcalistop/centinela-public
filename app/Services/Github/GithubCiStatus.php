<?php

declare(strict_types=1);

namespace App\Services\Github;

use Carbon\CarbonImmutable;

final class GithubCiStatus
{
    /**
     * @param  'success'|'failure'|'pending'|'unknown'  $state
     */
    public function __construct(
        public readonly string $state,
        public readonly ?string $conclusion,
        public readonly ?string $status,
        public readonly ?string $workflowName,
        public readonly ?string $headSha,
        public readonly ?string $headShaShort,
        public readonly ?string $htmlUrl,
        public readonly ?CarbonImmutable $runAt,
        public readonly bool $stale,
        public readonly ?string $errorMessage = null,
    ) {}
}
