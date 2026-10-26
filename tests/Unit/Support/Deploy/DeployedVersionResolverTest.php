<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Deploy;

use App\Support\Deploy\DeployedVersionResolver;
use PHPUnit\Framework\TestCase;

class DeployedVersionResolverTest extends TestCase
{
    /** @test */
    public function parse_describe_output_extracts_semver_prefix(): void
    {
        $resolver = new DeployedVersionResolver;

        $this->assertSame('v4.3.0', $resolver->parseDescribeOutput('v4.3.0-23-g1207a59'));
        $this->assertSame('v4.3.0', $resolver->parseDescribeOutput('v4.3.0'));
        $this->assertNull($resolver->parseDescribeOutput('not-a-version'));
    }
}
