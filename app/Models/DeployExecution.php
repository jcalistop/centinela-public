<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Deploy\DeployExecutionMode;
use App\Support\Deploy\DeployStepRecorder;
use App\Support\Releases\ReleaseSemVer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeployExecution extends Model
{
    protected $table = 'deploy_executions';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'mode',
        'status',
        'actor_user_id',
        'from_sha',
        'to_sha',
        'branch',
        'backup_status',
        'migrate_status',
        'rollback_status',
        'summary',
        'meta',
        'started_at',
        'finished_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected static function booted(): void
    {
        static::saving(function (DeployExecution $execution): void {
            $summary = is_string($execution->summary) ? $execution->summary : null;
            $meta = is_array($execution->meta) ? $execution->meta : null;
            $result = DeployStepRecorder::scrubExecutionFields($summary, $meta);

            if (! $result['changed']) {
                return;
            }

            $execution->summary = $result['summary'];
            if (is_array($result['meta'])) {
                $execution->meta = $result['meta'];
            }
        });
    }

    public function displayVersion(): ?string
    {
        $meta = is_array($this->meta) ? $this->meta : [];

        $key = DeployExecutionMode::isNotificationMode((string) $this->mode)
            ? 'notified_version'
            : 'deployed_version';

        $raw = $meta[$key] ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $normalized = ReleaseSemVer::normalizeTag($raw);

        return $normalized !== '' ? $normalized : null;
    }
}
