<?php

declare(strict_types=1);

namespace App\Services\Releases;

use App\Models\DeployExecution;
use App\Support\Deploy\DeployedVersionResolver;
use App\Support\Deploy\DeployExecutionMode;
use App\Support\Releases\ReleaseSemVer;
use Carbon\CarbonImmutable;

class DeployedReleaseState
{
    public function __construct(
        private readonly DeployedVersionResolver $deployedVersionResolver,
    ) {}

    public function deployedVersion(): ?string
    {
        $execution = $this->latestDeployExecution();
        if ($execution === null) {
            return null;
        }

        $meta = is_array($execution->meta) ? $execution->meta : [];
        $fromMeta = isset($meta['deployed_version']) && is_string($meta['deployed_version'])
            ? ReleaseSemVer::normalizeTag($meta['deployed_version'])
            : '';

        if ($fromMeta !== '' && ReleaseSemVer::parse($fromMeta) !== null) {
            return $fromMeta;
        }

        if ($this->shouldResolveDeployedVersionFromGit()) {
            $fromSha = is_string($execution->to_sha) && $execution->to_sha !== ''
                ? $this->deployedVersionResolver->resolveTagFromSha($execution->to_sha)
                : null;

            if ($fromSha !== null) {
                return $fromSha;
            }
        }

        return null;
    }

    public function deployedAt(): ?CarbonImmutable
    {
        $execution = $this->latestDeployExecution();
        if ($execution === null) {
            return null;
        }

        $meta = is_array($execution->meta) ? $execution->meta : [];
        if (isset($meta['deployed_at']) && is_string($meta['deployed_at']) && $meta['deployed_at'] !== '') {
            try {
                return CarbonImmutable::parse($meta['deployed_at']);
            } catch (\Throwable) {
                // fall through
            }
        }

        $finished = $execution->finished_at;

        return $finished !== null ? CarbonImmutable::parse($finished) : null;
    }

    public function notifiedVersion(): ?string
    {
        $execution = DeployExecution::query()
            ->where('mode', DeployExecutionMode::RELEASE_NOTIFY)
            ->where('status', 'succeeded')
            ->orderByDesc('finished_at')
            ->first();

        if ($execution === null) {
            return null;
        }

        $meta = is_array($execution->meta) ? $execution->meta : [];
        $version = isset($meta['notified_version']) && is_string($meta['notified_version'])
            ? ReleaseSemVer::normalizeTag($meta['notified_version'])
            : '';

        return $version !== '' ? $version : null;
    }

    public function pendingNotification(): bool
    {
        $deployed = $this->deployedVersion();
        if ($deployed === null) {
            return false;
        }

        $notified = $this->notifiedVersion();
        if ($notified === null) {
            return true;
        }

        return ReleaseSemVer::compareTags($deployed, $notified) > 0;
    }

    public function displayVersion(): string
    {
        $deployed = $this->deployedVersion();

        return $deployed ?? '';
    }

    public function latestDeployExecution(): ?DeployExecution
    {
        return DeployExecution::query()
            ->whereIn('mode', DeployExecutionMode::deployModes())
            ->where('status', 'succeeded')
            ->orderByDesc('finished_at')
            ->first();
    }

    private function shouldResolveDeployedVersionFromGit(): bool
    {
        if ((bool) config('centinela.deploy.git_status_via_http', false)) {
            return true;
        }

        return ! $this->isServingHttpRequest();
    }

    private function isServingHttpRequest(): bool
    {
        if (! app()->bound('request')) {
            return false;
        }

        return in_array(request()->getMethod(), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true);
    }
}
