<?php

declare(strict_types=1);

namespace App\Services\Github;

use App\Models\GithubReleaseSnapshot;
use App\Services\Releases\DeployedReleaseState;
use App\Support\Releases\ReleaseSemVer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GithubReleasesService
{
    private const CACHE_KEY_LIVE = 'github.releases:live';

    private const CACHE_KEY_LAST_GOOD = 'github.releases:last_good';

    private const CACHE_KEY_API_PROBE = 'github.releases:api_probe:v1';

    private const API_PROBE_TTL_SECONDS = 120;

    public function __construct(
        private readonly DeployedReleaseState $deployedReleaseState,
    ) {}

    public function getPageData(): GithubReleasesPageData
    {
        $fetched = $this->fetchReleasesList();
        $deployedVersion = $this->deployedReleaseState->deployedVersion();
        $deployedAt = $this->deployedReleaseState->deployedAt();

        $filtered = ReleaseSemVer::filterUpTo($deployedVersion, $fetched['releases']);

        return new GithubReleasesPageData(
            $filtered,
            $fetched['fromStaleCache'],
            $fetched['syncedAt'],
            $fetched['unavailable'],
            $deployedVersion,
            $deployedAt,
        );
    }

    /**
     * @return array{releases: list<array<string, mixed>>, synced_at: CarbonImmutable}|null
     */
    public function syncFromApi(): ?array
    {
        $fetched = $this->tryFetchFromApi();
        if ($fetched === null) {
            return null;
        }

        $this->persistSuccessfulFetch($fetched);

        return $fetched;
    }

    public function apiProbeSucceeded(): bool
    {
        return (bool) Cache::remember(self::CACHE_KEY_API_PROBE, self::API_PROBE_TTL_SECONDS, function (): bool {
            $repository = (string) config('services.github.releases_repository', 'jcalistop/minsal-centinela');
            $url = "https://api.github.com/repos/{$repository}/releases?per_page=1";

            try {
                $response = $this->httpClient()->get($url);

                return $response->successful();
            } catch (\Throwable) {
                return false;
            }
        });
    }

    public function lastSnapshotSyncedAt(): ?CarbonImmutable
    {
        $fromCache = $this->parseSyncedAt(Cache::get(self::CACHE_KEY_LAST_GOOD)['synced_at'] ?? null);
        if ($fromCache !== null) {
            return $fromCache;
        }

        $latest = GithubReleaseSnapshot::query()->orderByDesc('synced_at')->value('synced_at');
        if ($latest === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($latest);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{id: int|string, tag_name: string, name: string, published_at: ?string, display_date: ?string, body: string}|null
     */
    public function releaseForTag(string $tag): ?array
    {
        $tag = ReleaseSemVer::normalizeTag($tag);
        if ($tag === '') {
            return null;
        }

        foreach ($this->allCachedReleases() as $release) {
            if (ReleaseSemVer::normalizeTag((string) ($release['tag_name'] ?? '')) === $tag) {
                return $release;
            }
        }

        $snapshot = GithubReleaseSnapshot::query()->where('tag_name', $tag)->first();
        if ($snapshot !== null) {
            return $snapshot->toReleaseArray();
        }

        $fetched = $this->tryFetchFromApi();
        if ($fetched !== null) {
            $this->persistSuccessfulFetch($fetched);
            foreach ($fetched['releases'] as $release) {
                if (ReleaseSemVer::normalizeTag((string) ($release['tag_name'] ?? '')) === $tag) {
                    return $release;
                }
            }
        }

        return null;
    }

    public function hasReleaseBodyForTag(string $tag): bool
    {
        $release = $this->releaseForTag($tag);

        return $release !== null && trim((string) ($release['body'] ?? '')) !== '';
    }

    public function latestPublishedRelease(): GithubLatestReleaseSummary
    {
        $fetched = $this->fetchReleasesList();
        $releases = $fetched['releases'];

        if ($releases === []) {
            $errorMessage = $fetched['unavailable']
                ? 'Sin releases disponibles (API, red o sincronización).'
                : null;

            return new GithubLatestReleaseSummary(
                tag: null,
                name: null,
                publishedAt: null,
                htmlUrl: null,
                stale: $fetched['fromStaleCache'],
                unavailable: $fetched['unavailable'],
                syncedAt: $fetched['syncedAt'],
                errorMessage: $errorMessage,
            );
        }

        $latest = $releases[0];
        $tag = ReleaseSemVer::normalizeTag((string) ($latest['tag_name'] ?? ''));
        $name = trim((string) ($latest['name'] ?? ''));
        $name = $name !== '' ? $name : null;

        $publishedAt = null;
        $publishedRaw = $latest['published_at'] ?? null;
        if (is_string($publishedRaw) && $publishedRaw !== '') {
            try {
                $publishedAt = CarbonImmutable::parse($publishedRaw);
            } catch (\Throwable) {
                $publishedAt = null;
            }
        }

        $repository = (string) config('services.github.releases_repository', 'jcalistop/minsal-centinela');
        $htmlUrl = $tag !== ''
            ? 'https://github.com/'.$repository.'/releases/tag/'.rawurlencode($tag)
            : null;

        return new GithubLatestReleaseSummary(
            tag: $tag !== '' ? $tag : null,
            name: $name,
            publishedAt: $publishedAt,
            htmlUrl: $htmlUrl,
            stale: $fetched['fromStaleCache'],
            unavailable: false,
            syncedAt: $fetched['syncedAt'],
        );
    }

    /**
     * @return array{releases: list<array<string, mixed>>, fromStaleCache: bool, syncedAt: ?CarbonImmutable, unavailable: bool}
     */
    private function fetchReleasesList(): array
    {
        $live = Cache::get(self::CACHE_KEY_LIVE);
        if (is_array($live) && isset($live['releases'], $live['synced_at']) && is_array($live['releases'])) {
            return [
                'releases' => $live['releases'],
                'fromStaleCache' => false,
                'syncedAt' => $this->parseSyncedAt($live['synced_at']),
                'unavailable' => false,
            ];
        }

        $fetched = $this->tryFetchFromApi();
        if ($fetched !== null) {
            $this->persistSuccessfulFetch($fetched);

            return [
                'releases' => $fetched['releases'],
                'fromStaleCache' => false,
                'syncedAt' => $fetched['synced_at'],
                'unavailable' => false,
            ];
        }

        $lastGood = Cache::get(self::CACHE_KEY_LAST_GOOD);
        if (is_array($lastGood) && isset($lastGood['releases'], $lastGood['synced_at']) && is_array($lastGood['releases'])) {
            return [
                'releases' => $lastGood['releases'],
                'fromStaleCache' => true,
                'syncedAt' => $this->parseSyncedAt($lastGood['synced_at']),
                'unavailable' => false,
            ];
        }

        $fromDb = $this->releasesFromSnapshots();
        if ($fromDb['releases'] !== []) {
            return [
                'releases' => $fromDb['releases'],
                'fromStaleCache' => true,
                'syncedAt' => $fromDb['syncedAt'],
                'unavailable' => false,
            ];
        }

        return [
            'releases' => [],
            'fromStaleCache' => false,
            'syncedAt' => null,
            'unavailable' => true,
        ];
    }

    /**
     * @return array{releases: list<array<string, mixed>>, syncedAt: ?CarbonImmutable}
     */
    private function releasesFromSnapshots(): array
    {
        $rows = GithubReleaseSnapshot::query()
            ->orderByDesc('published_at')
            ->get();

        if ($rows->isEmpty()) {
            return ['releases' => [], 'syncedAt' => null];
        }

        $syncedAt = null;
        $releases = [];
        foreach ($rows as $row) {
            $releases[] = $row->toReleaseArray();
            if ($syncedAt === null || ($row->synced_at !== null && $row->synced_at->gt($syncedAt))) {
                $syncedAt = $row->synced_at !== null ? CarbonImmutable::parse($row->synced_at) : null;
            }
        }

        return ['releases' => $releases, 'syncedAt' => $syncedAt];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allCachedReleases(): array
    {
        $live = Cache::get(self::CACHE_KEY_LIVE);
        if (is_array($live['releases'] ?? null)) {
            return $live['releases'];
        }

        $lastGood = Cache::get(self::CACHE_KEY_LAST_GOOD);
        if (is_array($lastGood['releases'] ?? null)) {
            return $lastGood['releases'];
        }

        return $this->releasesFromSnapshots()['releases'];
    }

    /**
     * @return array{releases: list<array{id: int|string, tag_name: string, name: string, published_at: ?string, display_date: ?string, body: string}>, synced_at: CarbonImmutable}|null
     */
    private function tryFetchFromApi(): ?array
    {
        $repository = (string) config('services.github.releases_repository', 'jcalistop/minsal-centinela');
        $url = "https://api.github.com/repos/{$repository}/releases";

        try {
            $response = $this->httpClient()->get($url);

            if (! $response->successful()) {
                Log::warning('GitHub releases list request failed', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body() ?? '', 0, 200),
                ]);

                return null;
            }

            $releases = $this->normalizeList($response->json());

            return [
                'releases' => $releases,
                'synced_at' => CarbonImmutable::now(),
            ];
        } catch (RequestException $e) {
            Log::error('GitHub releases HTTP error: '.$e->getMessage());

            return null;
        } catch (\Throwable $e) {
            Log::error('GitHub releases unexpected error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return list<array{id: int|string, tag_name: string, name: string, published_at: ?string, display_date: ?string, body: string}>
     */
    private function normalizeList(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $out = [];
        foreach ($json as $row) {
            if (! is_array($row)) {
                continue;
            }

            $tagName = isset($row['tag_name']) && is_string($row['tag_name']) ? $row['tag_name'] : '';
            $name = isset($row['name']) && is_string($row['name']) ? $row['name'] : '';
            $publishedAt = isset($row['published_at']) && is_string($row['published_at']) ? $row['published_at'] : null;
            $body = isset($row['body']) && is_string($row['body']) ? $row['body'] : '';

            $id = $row['id'] ?? null;
            if (! is_int($id) && ! is_string($id)) {
                $slugBase = $tagName !== '' ? $tagName : ($name !== '' ? $name : serialize($row));
                $id = 'synthetic-'.substr(sha1($slugBase), 0, 12);
            }

            $displayDate = null;
            if ($publishedAt !== null && $publishedAt !== '') {
                try {
                    $displayDate = CarbonImmutable::parse($publishedAt)
                        ->timezone((string) config('app.timezone'))
                        ->format('d/m/Y');
                } catch (\Throwable) {
                    $displayDate = null;
                }
            }

            $out[] = [
                'id' => $id,
                'tag_name' => $tagName,
                'name' => $name,
                'published_at' => $publishedAt,
                'display_date' => $displayDate,
                'body' => $body,
            ];
        }

        usort($out, function (array $a, array $b): int {
            $ta = (string) ($a['published_at'] ?? '');
            $tb = (string) ($b['published_at'] ?? '');

            return strcmp($tb, $ta);
        });

        return array_values($out);
    }

    /**
     * @param  array{releases: list<array<string, mixed>>, synced_at: CarbonImmutable}  $fetched
     */
    private function persistSuccessfulFetch(array $fetched): void
    {
        $ttlSeconds = max(60, (int) config('services.github.releases_http_cache_seconds', 3600));
        $payload = [
            'releases' => $fetched['releases'],
            'synced_at' => $fetched['synced_at']->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY_LIVE, $payload, now()->addSeconds($ttlSeconds));
        Cache::put(self::CACHE_KEY_LAST_GOOD, $payload, now()->addYears(10));

        foreach ($fetched['releases'] as $release) {
            if (! is_array($release) || empty($release['tag_name'])) {
                continue;
            }

            try {
                GithubReleaseSnapshot::upsertFromNormalizedRelease($release, $fetched['synced_at']);
            } catch (\Throwable $e) {
                Log::warning('Failed to upsert github release snapshot', [
                    'tag' => $release['tag_name'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function httpClient(): PendingRequest
    {
        $client = Http::withoutVerifying()
            ->timeout(15)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => (string) config('app.name', 'Laravel'),
            ]);

        $accessToken = config('services.github.releases_token');
        if (! empty($accessToken)) {
            $client = $client->withToken((string) $accessToken);
        }

        return $client;
    }

    private function parseSyncedAt(mixed $value): ?CarbonImmutable
    {
        if (is_string($value) && $value !== '') {
            try {
                return CarbonImmutable::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    public static function anchorIdForRelease(array $release): string
    {
        $id = $release['id'] ?? 'unknown';

        return 'release-'.preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $id);
    }

    public static function labelForRelease(array $release): string
    {
        $tag = trim((string) ($release['tag_name'] ?? ''));
        if ($tag !== '') {
            return $tag;
        }

        $name = trim((string) ($release['name'] ?? ''));

        return $name !== '' ? $name : 'Release';
    }
}
