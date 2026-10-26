<?php

declare(strict_types=1);

namespace App\Services\Github;

use Carbon\CarbonImmutable;

final class GithubReleasesPageData
{
    /**
     * @param  list<array{id: int|string, tag_name: string, name: string, published_at: ?string, display_date: ?string, body: string}>  $releases
     */
    public function __construct(
        public readonly array $releases,
        public readonly bool $fromStaleCache,
        public readonly ?CarbonImmutable $syncedAt,
        public readonly bool $unavailable,
        public readonly ?string $deployedVersion,
        public readonly ?CarbonImmutable $deployedAt,
    ) {}
}
