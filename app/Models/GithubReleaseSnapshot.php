<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class GithubReleaseSnapshot extends Model
{
    protected $table = 'github_release_snapshots';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'tag_name',
        'name',
        'body',
        'published_at',
        'github_release_id',
        'synced_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'published_at' => 'datetime',
        'synced_at' => 'datetime',
        'github_release_id' => 'integer',
    ];

    /**
     * @param  array{id: int|string, tag_name: string, name: string, published_at: ?string, display_date: ?string, body: string}  $release
     */
    public static function upsertFromNormalizedRelease(array $release, ?CarbonImmutable $syncedAt = null): self
    {
        $syncedAt ??= CarbonImmutable::now();
        $publishedAt = null;
        $publishedRaw = $release['published_at'] ?? null;
        if (is_string($publishedRaw) && $publishedRaw !== '') {
            try {
                $publishedAt = CarbonImmutable::parse($publishedRaw);
            } catch (\Throwable) {
                $publishedAt = null;
            }
        }

        $githubId = $release['id'] ?? null;
        $githubReleaseId = is_int($githubId) ? $githubId : (is_numeric($githubId) ? (int) $githubId : null);

        return self::query()->updateOrCreate(
            ['tag_name' => (string) $release['tag_name']],
            [
                'name' => (string) ($release['name'] ?? ''),
                'body' => (string) ($release['body'] ?? ''),
                'published_at' => $publishedAt,
                'github_release_id' => $githubReleaseId,
                'synced_at' => $syncedAt,
            ],
        );
    }

    /**
     * @return array{id: int|string, tag_name: string, name: string, published_at: ?string, display_date: ?string, body: string}
     */
    public function toReleaseArray(): array
    {
        $publishedAt = $this->published_at?->toIso8601String();
        $displayDate = null;
        if ($this->published_at !== null) {
            $displayDate = $this->published_at
                ->timezone((string) config('app.timezone'))
                ->format('d/m/Y');
        }

        $id = $this->github_release_id ?? ('snapshot-'.$this->id);

        return [
            'id' => $id,
            'tag_name' => (string) $this->tag_name,
            'name' => (string) ($this->name ?? ''),
            'published_at' => $publishedAt,
            'display_date' => $displayDate,
            'body' => (string) $this->body,
        ];
    }
}
