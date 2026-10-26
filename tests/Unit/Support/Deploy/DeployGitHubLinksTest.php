<?php

namespace Tests\Unit\Support\Deploy;

use App\Support\Deploy\DeployGitHubLinks;
use Tests\TestCase;

class DeployGitHubLinksTest extends TestCase
{
    /** @test */
    public function commit_url_points_to_github_commit(): void
    {
        config(['services.github.releases_repository' => 'org/repo']);

        $links = new DeployGitHubLinks;

        $this->assertSame(
            'https://github.com/org/repo/commit/abc123def456',
            $links->commitUrl('abc123def456')
        );
    }

    /** @test */
    public function compare_url_uses_github_compare_range(): void
    {
        config(['services.github.releases_repository' => 'org/repo']);

        $links = new DeployGitHubLinks;

        $this->assertSame(
            'https://github.com/org/repo/compare/aaa111...bbb222',
            $links->compareUrl('aaa111', 'bbb222')
        );
    }

    /** @test */
    public function compare_url_is_null_when_shas_missing_or_equal(): void
    {
        $links = new DeployGitHubLinks;

        $this->assertNull($links->compareUrl(null, 'bbb'));
        $this->assertNull($links->compareUrl('aaa', 'aaa'));
    }
}
