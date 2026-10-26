<?php

namespace App\Livewire\Admin;

use App\Jobs\NotifyControlGestionDeployedRelease;
use App\Jobs\RunManualDeployJob;
use App\Models\DeployExecution;
use App\Models\User;
use App\Services\Github\GithubCiStatusService;
use App\Services\Github\GithubReleasesService;
use App\Services\Releases\DeployedReleaseState;
use App\Services\Releases\ReleaseNotificationService;
use App\Support\Deploy\DeployGitHubLinks;
use App\Support\Deploy\DeployOrchestrator;
use App\Support\Releases\ReleaseSemVer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class DeployDashboard extends Component
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function placeholder(array $params = []): string
    {
        return '<div class="panel panel-bordered" aria-busy="true">'
            .'<div class="panel-heading"><h3 class="panel-title">Deploy</h3></div>'
            .'<div class="panel-body text-muted">Cargando panel de deploy…</div>'
            .'</div>';
    }

    public bool $queued = false;

    public bool $skipped = false;

    public ?string $feedbackMessage = null;

    public ?string $queueError = null;

    public ?string $feedbackExpiresAt = null;

    public ?int $selectedExecutionId = null;

    public bool $confirmResendNotify = false;

    /**
     * @return array<int, DeployExecution>
     */
    public function executions(): array
    {
        return DeployExecution::query()
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->all();
    }

    public function sendTestReleaseMail(
        ReleaseNotificationService $notification,
        DeployedReleaseState $deployedState,
        GithubReleasesService $githubReleases,
    ): void {
        $this->clearFeedback();
        $user = $this->requireAdmin();

        $version = $deployedState->deployedVersion();
        if ($version === null) {
            $this->queueError = 'No hay versión desplegada registrada.';

            return;
        }

        $execution = $notification->startNotificationExecution($user, isTest: true);
        NotifyControlGestionDeployedRelease::dispatch($execution->id, isTest: true);
        $this->setFeedback('Correo de prueba encolado para administradores.');
    }

    public function notifyControlGestion(
        ReleaseNotificationService $notification,
        DeployedReleaseState $deployedState,
        GithubReleasesService $githubReleases,
    ): void {
        $this->clearFeedback();
        $user = $this->requireAdmin();

        $version = $deployedState->deployedVersion();
        if ($version === null) {
            $this->queueError = 'No hay versión desplegada registrada.';

            return;
        }

        $notified = $deployedState->notifiedVersion();
        if ($notified !== null && ReleaseSemVer::compareTags($version, $notified) === 0 && ! $this->confirmResendNotify) {
            $this->queueError = 'Esta versión ya fue notificada. Marque «Reenviar» para confirmar.';

            return;
        }

        if (! $githubReleases->hasReleaseBodyForTag($version)) {
            $this->queueError = 'No hay notas de versión publicadas para '.$version.'. Ejecute centinela:github:sync-releases si aplica.';

            return;
        }

        $execution = $notification->startNotificationExecution($user, isTest: false);
        NotifyControlGestionDeployedRelease::dispatch(
            $execution->id,
            isTest: false,
            forceResend: $this->confirmResendNotify,
        );
        $this->confirmResendNotify = false;
        $this->setFeedback('Notificación oficial encolada para control-gestión.');
    }

    private function requireAdmin(): User
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasRole('admin')) {
            abort(403);
        }

        return $user;
    }

    public function isAutoEnabled(): bool
    {
        return (bool) config('centinela.deploy.automatic_enabled', false);
    }

    public function isAutoBlocked(): bool
    {
        if (! $this->isAutoEnabled()) {
            return false;
        }

        return Cache::get(DeployOrchestrator::SCHEDULE_LOCK_KEY) === '1';
    }

    public function pendingReleasesJobs(): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        return (int) DB::table('jobs')->where('queue', 'releases')->count();
    }

    public function refreshDashboard(): void
    {
        if ($this->feedbackExpiresAt !== null && now()->greaterThan(Carbon::parse($this->feedbackExpiresAt))) {
            $this->clearFeedback();
        }
    }

    public function dismissFeedback(): void
    {
        $this->clearFeedback();
    }

    public function unlockAutoSchedule(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasRole('admin')) {
            abort(403);
        }

        Cache::forget(DeployOrchestrator::SCHEDULE_LOCK_KEY);
        $this->clearFeedback();
        $this->setFeedback('Bloqueo de deploy automático liberado.');
        $this->skipped = false;
        $this->queued = false;
    }

    public function showExecutionDetail(int $executionId): void
    {
        $exists = DeployExecution::query()->whereKey($executionId)->exists();
        $this->selectedExecutionId = $exists ? $executionId : null;
    }

    public function closeExecutionDetail(): void
    {
        $this->selectedExecutionId = null;
    }

    public function requestManualDeploy(DeployOrchestrator $deploy): void
    {
        $this->clearFeedback();

        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        if (! $user->hasRole('admin')) {
            abort(403);
        }

        try {
            $branchMismatch = $deploy->branchMismatchSummary();
            if ($branchMismatch !== null) {
                $this->queueError = $branchMismatch;

                return;
            }

            $ciGate = $deploy->ciGateBlockSummary('manual');
            if ($ciGate !== null) {
                $this->queueError = $ciGate;

                return;
            }

            if ($deploy->assessIdempotentDeploy()['skip']) {
                $result = $deploy->runManual($user);
                if ($result['ok']) {
                    $this->skipped = true;
                    $this->setFeedback($result['summary']);

                    return;
                }

                $this->queueError = $result['summary'];

                return;
            }

            $execution = DeployExecution::query()->create([
                'mode' => 'manual',
                'status' => 'queued',
                'actor_user_id' => $user->id,
                'branch' => (string) config('centinela.deploy.branch', 'main'),
                'summary' => 'En cola releases; pendiente de worker.',
                'meta' => [
                    'queue' => 'releases',
                    'dispatched_at' => now()->toIso8601String(),
                ],
            ]);

            try {
                RunManualDeployJob::dispatch($execution->id, $user->id);
            } catch (\Throwable $e) {
                $execution->status = 'failed_precondition';
                $execution->summary = 'No se pudo encolar el job: '.$e->getMessage();
                $execution->started_at = now();
                $execution->finished_at = now();
                $execution->meta = array_merge(is_array($execution->meta) ? $execution->meta : [], [
                    'reason' => 'dispatch_failed',
                ]);
                $execution->save();

                throw $e;
            }

            $this->queued = true;
            $this->setFeedback(
                'Solicitud encolada (#'.$execution->id.') en la cola releases. '
                    .'Se procesará en la próxima ejecución diaria del servidor (cron_mail.bat) o vía CLI en consola.'
            );
        } catch (\Throwable $e) {
            $this->queueError = $e->getMessage();
        }
    }

    private function setFeedback(string $message): void
    {
        $this->feedbackMessage = $message;
        $this->feedbackExpiresAt = now()->addSeconds(8)->toIso8601String();
    }

    private function clearFeedback(): void
    {
        $this->queued = false;
        $this->skipped = false;
        $this->feedbackMessage = null;
        $this->queueError = null;
        $this->feedbackExpiresAt = null;
    }

    public function selectedExecution(): ?DeployExecution
    {
        if ($this->selectedExecutionId === null) {
            return null;
        }

        return DeployExecution::query()
            ->with('actor')
            ->find($this->selectedExecutionId);
    }

    public function render(
        DeployOrchestrator $deploy,
        GithubCiStatusService $githubCi,
        DeployGitHubLinks $gitHubLinks,
        DeployedReleaseState $deployedState,
        GithubReleasesService $githubReleases,
    ): View {
        $deployedVersion = $deployedState->deployedVersion();
        $notifiedVersion = $deployedState->notifiedVersion();

        $actor = Auth::user();
        $ciStatus = $githubCi->latestForDeployBranch();
        $ciGateBlockMessage = $deploy->ciGateBlockSummary('manual');
        $ciGateBlocksManual = $ciGateBlockMessage !== null;

        return view('livewire.admin.deploy-dashboard', [
            'executions' => $this->executions(),
            'selectedExecution' => $this->selectedExecution(),
            'actorUserId' => $actor instanceof User ? $actor->id : null,
            'actorUserEmail' => $actor instanceof User ? (string) $actor->email : null,
            'deployExecutionsRetentionDays' => (int) config('centinela.deploy.executions_retention_days', 90),
            'autoEnabled' => $this->isAutoEnabled(),
            'autoBlocked' => $this->isAutoBlocked(),
            'pendingReleasesJobs' => $this->pendingReleasesJobs(),
            'ciStatus' => $ciStatus,
            'ciGateMode' => $githubCi->normalizedRequireCiMode(),
            'ciGateBlocksManual' => $ciGateBlocksManual,
            'ciGateBlockMessage' => $ciGateBlockMessage,
            'deployBranch' => $deploy->deployBranch(),
            'checkoutBranch' => $deploy->currentCheckoutBranch(),
            'branchAligned' => $deploy->isCheckoutAlignedWithDeployBranch(),
            'branchMismatchMessage' => $deploy->branchMismatchSummary(),
            'gitStatusViaHttp' => $deploy->gitStatusReadableViaHttp(),
            'gitHubLinks' => $gitHubLinks,
            'deployedVersion' => $deployedVersion,
            'deployedAt' => $deployedState->deployedAt(),
            'notifiedVersion' => $notifiedVersion,
            'pendingCgNotification' => $deployedState->pendingNotification(),
            'githubReleasesReachable' => $githubReleases->apiProbeSucceeded(),
            'lastReleasesSyncAt' => $githubReleases->lastSnapshotSyncedAt(),
            'latestGithubRelease' => $githubReleases->latestPublishedRelease(),
            'canNotifyOfficial' => $deployedVersion !== null && $githubReleases->hasReleaseBodyForTag($deployedVersion),
        ]);
    }
}
