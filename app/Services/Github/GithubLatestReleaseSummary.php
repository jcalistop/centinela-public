<?php

declare(strict_types=1);

namespace App\Services\Github;

use App\Support\Releases\ReleaseSemVer;
use Carbon\CarbonImmutable;

final class GithubLatestReleaseSummary
{
    public function __construct(
        public readonly ?string $tag,
        public readonly ?string $name,
        public readonly ?CarbonImmutable $publishedAt,
        public readonly ?string $htmlUrl,
        public readonly bool $stale,
        public readonly bool $unavailable,
        public readonly ?CarbonImmutable $syncedAt,
        public readonly ?string $errorMessage = null,
    ) {}

    public function isAheadOf(?string $deployedVersion): bool
    {
        if ($this->tag === null || $this->tag === '') {
            return false;
        }

        if ($deployedVersion === null || $deployedVersion === '') {
            return true;
        }

        return ReleaseSemVer::compareTags($this->tag, $deployedVersion) > 0;
    }
}
