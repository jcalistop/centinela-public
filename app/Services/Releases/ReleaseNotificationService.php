<?php

declare(strict_types=1);

namespace App\Services\Releases;

use App\Mail\ControlGestionReleaseDeployedMail;
use App\Models\DeployExecution;
use App\Models\User;
use App\Services\Github\GithubReleasesService;
use App\Support\Deploy\DeployExecutionMode;
use App\Support\Releases\ReleaseSemVer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReleaseNotificationService
{
    public const CONTENT_API = 'api';

    public const CONTENT_SNAPSHOT_DB = 'snapshot_db';

    public const CONTENT_FALLBACK = 'fallback';

    public function __construct(
        private readonly DeployedReleaseState $deployedReleaseState,
        private readonly GithubReleasesService $githubReleasesService,
    ) {}

    public function executeNotification(int $executionId, bool $isTest, bool $forceResend = false): void
    {
        $execution = DeployExecution::query()->find($executionId);
        if ($execution === null) {
            return;
        }

        try {
            $version = $this->deployedReleaseState->deployedVersion();
            if ($version === null || $version === '') {
                throw new \RuntimeException('No hay versión desplegada registrada.');
            }

            if (! $isTest) {
                $notified = $this->deployedReleaseState->notifiedVersion();
                if (! $forceResend && $notified !== null && ReleaseSemVer::compareTags($version, $notified) === 0) {
                    throw new \RuntimeException('Esta versión ya fue notificada a control-gestión.');
                }
            }

            $content = $this->resolveContent($version);
            $recipients = $isTest ? $this->adminRecipients() : $this->controlGestionRecipients();

            if ($recipients->isEmpty()) {
                throw new \RuntimeException('No hay destinatarios con correo válido.');
            }

            if (! $isTest && $content['content_source'] === self::CONTENT_FALLBACK) {
                throw new \RuntimeException('No hay notas de versión disponibles para el envío oficial.');
            }

            $releasesUrl = route('acerca-de.releases');
            $sent = 0;
            foreach ($recipients as $user) {
                Mail::to($user->email)->queue(new ControlGestionReleaseDeployedMail(
                    version: $version,
                    releasesUrl: $releasesUrl,
                    bodyMarkdown: $content['body'],
                    isTest: $isTest,
                    recipientName: (string) $user->name,
                ));
                $sent++;
            }

            $execution->status = 'succeeded';
            $execution->summary = $isTest
                ? "Correo de prueba encolado ({$sent} admin)."
                : "Notificación a control-gestión encolada ({$sent} destinatarios).";
            $execution->finished_at = now();
            $execution->meta = array_merge(is_array($execution->meta) ? $execution->meta : [], [
                'notified_version' => $version,
                'recipient_count' => $sent,
                'content_source' => $content['content_source'],
                'is_test' => $isTest,
            ]);
            $execution->save();
        } catch (\Throwable $e) {
            Log::error('Release notification failed', [
                'execution_id' => $executionId,
                'error' => $e->getMessage(),
            ]);

            $execution->status = 'failed';
            $execution->summary = 'Notificación falló: '.$e->getMessage();
            $execution->finished_at = now();
            $execution->meta = array_merge(is_array($execution->meta) ? $execution->meta : [], [
                'error' => $e->getMessage(),
                'is_test' => $isTest,
            ]);
            $execution->save();
        }
    }

    /**
     * @return array{body: string, content_source: string}
     */
    public function resolveContent(string $version): array
    {
        $version = ReleaseSemVer::normalizeTag($version);
        $release = $this->githubReleasesService->releaseForTag($version);
        $body = trim((string) ($release['body'] ?? ''));

        if ($body !== '') {
            $source = $this->githubReleasesService->apiProbeSucceeded()
                ? self::CONTENT_API
                : self::CONTENT_SNAPSHOT_DB;

            return ['body' => $body, 'content_source' => $source];
        }

        return [
            'body' => "Se ha desplegado la versión **{$version}** en Centinela. Las notas detalladas no están disponibles en este momento; consulte la sección de versiones liberadas cuando estén publicadas.",
            'content_source' => self::CONTENT_FALLBACK,
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function adminRecipients(): Collection
    {
        return User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function controlGestionRecipients(): Collection
    {
        return User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereHas('role', fn ($q) => $q->where('name', 'control-gestion'))
            ->whereHas('cenHieDependency', fn ($q) => $q->where('habilitado', true))
            ->get();
    }

    public function startNotificationExecution(User $actor, bool $isTest): DeployExecution
    {
        $mode = $isTest ? DeployExecutionMode::RELEASE_NOTIFY_TEST : DeployExecutionMode::RELEASE_NOTIFY;

        return DeployExecution::query()->create([
            'mode' => $mode,
            'status' => 'running',
            'actor_user_id' => $actor->id,
            'branch' => (string) config('centinela.deploy.branch', 'main'),
            'backup_status' => 'skipped',
            'migrate_status' => 'skipped',
            'rollback_status' => 'skipped',
            'summary' => $isTest ? 'Correo de prueba en curso…' : 'Notificación a control-gestión en curso…',
            'started_at' => now(),
        ]);
    }
}
