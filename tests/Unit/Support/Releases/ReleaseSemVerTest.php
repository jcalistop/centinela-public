<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Releases;

use App\Support\Releases\ReleaseSemVer;
use PHPUnit\Framework\TestCase;

class ReleaseSemVerTest extends TestCase
{
    /** @test */
    public function filter_up_to_excludes_higher_versions(): void
    {
        $releases = [
            ['tag_name' => 'v2.0.0', 'published_at' => '2025-06-01T12:00:00Z'],
            ['tag_name' => 'v1.0.0', 'published_at' => '2024-01-15T12:00:00Z'],
            ['tag_name' => 'v3.0.0', 'published_at' => '2026-01-01T12:00:00Z'],
        ];

        $filtered = ReleaseSemVer::filterUpTo('v2.0.0', $releases);

        $tags = array_column($filtered, 'tag_name');
        $this->assertSame(['v2.0.0', 'v1.0.0'], $tags);
    }

    /** @test */
    public function filter_up_to_returns_empty_when_no_deployed_version(): void
    {
        $this->assertSame([], ReleaseSemVer::filterUpTo(null, [
            ['tag_name' => 'v1.0.0'],
        ]));
    }
}
