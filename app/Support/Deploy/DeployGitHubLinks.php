<?php

declare(strict_types=1);

namespace App\Support\Deploy;

final class DeployGitHubLinks
{
    public function repository(): string
    {
        $repo = (string) config('services.github.releases_repository', '');

        if ($repo !== '') {
            return $repo;
        }

        return (string) config('centinela.github_issues.repo', 'jcalistop/minsal-centinela');
    }

    public function commitUrl(?string $sha): ?string
    {
        if (! is_string($sha) || strlen(trim($sha)) < 7) {
            return null;
        }

        return 'https://github.com/'.$this->repository().'/commit/'.rawurlencode(trim($sha));
    }

    public function compareUrl(?string $baseSha, ?string $headSha): ?string
    {
        if (! is_string($baseSha) || ! is_string($headSha)) {
            return null;
        }

        $base = trim($baseSha);
        $head = trim($headSha);

        if ($base === '' || $head === '' || $base === $head) {
            return null;
        }

        return 'https://github.com/'.$this->repository().'/compare/'
            .rawurlencode($base).'...'.rawurlencode($head);
    }

    public function releaseTagUrl(?string $tag): ?string
    {
        if (! is_string($tag) || trim($tag) === '') {
            return null;
        }

        return 'https://github.com/'.$this->repository().'/releases/tag/'.rawurlencode(trim($tag));
    }

    public function releasesIndexUrl(): string
    {
        return 'https://github.com/'.$this->repository().'/releases';
    }
}
