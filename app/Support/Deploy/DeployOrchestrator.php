<?php

declare(strict_types=1);

namespace App\Support\Deploy;

use App\Models\DeployExecution;
use App\Models\User;
use App\Services\Github\GithubCiStatusService;
use App\Services\Github\GithubReleasesService;
use App\Support\Configuration\ConfigurationService;
use App\Support\Database\DatabaseBackupSkipGuard;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DeployOrchestrator
{
    public const SCHEDULE_LOCK_KEY = 'centinela:deploy:auto:block:v1';

    private ?Command $consoleCommand = null;

    public function __construct(
        private readonly ?DeployedVersionResolver $deployedVersionResolver = null,
        private readonly ?GithubReleasesService $githubReleasesService = null,
    ) {}

    public function setConsoleCommand(?Command $command): self
    {
        $this->consoleCommand = $command;

        return $this;
    }

    private function deployedVersionResolver(): DeployedVersionResolver
    {
        return $this->deployedVersionResolver ?? app(DeployedVersionResolver::class);
    }

    private function githubReleasesService(): GithubReleasesService
    {
        return $this->githubReleasesService ?? app(GithubReleasesService::class);
    }

    /**
     * @return array{ok: bool, execution_id: int|null, summary: string}
     */
    public function runAuto(): array
    {
        if (! (bool) config('centinela.deploy.automatic_enabled', false)) {
            $summary = 'Auto deploy deshabilitado.';
            $this->logEarlyExit(
                reason: 'auto_disabled',
                mode: 'auto',
                ok: true,
                summary: $summary,
                level: 'info',
            );

            return ['ok' => true, 'execution_id' => null, 'summary' => $summary];
        }

        if (Cache::get(self::SCHEDULE_LOCK_KEY) === '1') {
            $summary = 'Auto deploy bloqueado por falla previa.';
            $this->logEarlyExit(
                reason: 'auto_blocked',
                mode: 'auto',
                ok: false,
                summary: $summary,
                level: 'warning',
            );

            return ['ok' => false, 'execution_id' => null, 'summary' => $summary];
        }

        $todayKey = 'centinela:deploy:auto:last_success_date:v1';
        $today = now()->toDateString();
        if (Cache::get($todayKey) === $today) {
            $summary = 'Auto deploy ya fue exitoso hoy.';
            $this->logEarlyExit(
                reason: 'already_ok_today',
                mode: 'auto',
                ok: true,
                summary: $summary,
                level: 'info',
            );

            return ['ok' => true, 'execution_id' => null, 'summary' => $summary];
        }

        $result = $this->run(mode: 'auto', actor: null);
        if ($result['ok']) {
            Cache::put($todayKey, $today, now()->addDays(2));
        }

        Log::info('centinela.deploy.auto.finished', [
            'ok' => $result['ok'],
            'execution_id' => $result['execution_id'],
            'summary' => $result['summary'],
        ]);

        return $result;
    }

    /**
     * @return array{ok: bool, execution_id: int|null, summary: string}
     */
    public function runManual(User $actor, ?int $queuedExecutionId = null): array
    {
        $precreated = null;

        if ($queuedExecutionId !== null) {
            $precreated = DeployExecution::query()->find($queuedExecutionId);
            if (! $precreated instanceof DeployExecution) {
                return [
                    'ok' => false,
                    'execution_id' => $queuedExecutionId,
                    'summary' => 'Ejecución en cola no encontrada (#'.$queuedExecutionId.').',
                ];
            }
            if ((string) $precreated->status !== 'queued') {
                return [
                    'ok' => false,
                    'execution_id' => $precreated->id,
                    'summary' => 'La ejecución #'.$precreated->id.' no está en estado queued (actual: '.$precreated->status.').',
                ];
            }
        }

        return $this->run(mode: 'manual', actor: $actor, precreated: $precreated);
    }

    /**
     * Marca una ejecución queued como fallida (p. ej. actor inválido en el job).
     */
    public function failQueuedExecution(DeployExecution $execution, string $summary, array $meta = []): void
    {
        if ((string) $execution->status !== 'queued') {
            return;
        }

        $execution->status = 'failed_precondition';
        $execution->summary = $summary;
        $execution->finished_at = now();
        $execution->started_at = $execution->started_at ?? now();
        $execution->meta = array_merge(is_array($execution->meta) ? $execution->meta : [], $meta);
        $execution->save();

        $this->notifyAdminOps($execution);
    }

    public function deployBranch(): string
    {
        return (string) config('centinela.deploy.branch', 'main');
    }

    public function gitStatusReadableViaHttp(): bool
    {
        return (bool) config('centinela.deploy.git_status_via_http', false);
    }

    protected function shouldInvokeGitForStatusRead(): bool
    {
        if ($this->gitStatusReadableViaHttp()) {
            return true;
        }

        return ! $this->isServingHttpRequest();
    }

    protected function isServingHttpRequest(): bool
    {
        if (! app()->bound('request')) {
            return false;
        }

        return in_array(request()->getMethod(), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true);
    }

    protected function deferGitStatusCheckToDeployWorker(): bool
    {
        return ! $this->shouldInvokeGitForStatusRead();
    }

    public function currentCheckoutBranch(): ?string
    {
        if (! $this->shouldInvokeGitForStatusRead()) {
            return null;
        }

        try {
            $process = $this->runGit(['rev-parse', '--abbrev-ref', 'HEAD'], timeoutSeconds: 15);
            $branch = trim($process->getOutput());

            if ($branch === '' || $branch === 'HEAD') {
                return null;
            }

            return $branch;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isCheckoutAlignedWithDeployBranch(): bool
    {
        if ($this->deferGitStatusCheckToDeployWorker()) {
            return true;
        }

        $current = $this->currentCheckoutBranch();

        if ($current === null) {
            return false;
        }

        return $current === $this->deployBranch();
    }

    public function branchMismatchSummary(): ?string
    {
        if ($this->deferGitStatusCheckToDeployWorker()) {
            return null;
        }

        if ($this->isCheckoutAlignedWithDeployBranch()) {
            return null;
        }

        $configured = $this->deployBranch();
        $current = $this->currentCheckoutBranch();
        $diagnostic = $this->gitCheckoutDiagnostic();

        if ($current === null) {
            if (! $diagnostic['git_available']) {
                return 'Git no disponible o sin permisos en '.base_path()
                    .'. Verifique CENTINELA_GIT_BINARY, PATH y el usuario del proceso (TS-Producción / consola).';
            }

            $shaHint = is_string($diagnostic['head_sha_short']) && $diagnostic['head_sha_short'] !== ''
                ? ' HEAD='.$diagnostic['head_sha_short'].'.'
                : '';

            return 'Checkout en detached HEAD (git rev-parse --abbrev-ref HEAD → HEAD).'.$shaHint
                .' Se espera la rama '.$configured.'.';
        }

        return 'Checkout local en rama '.$current.' pero el deploy está configurado para '
            .$configured.'. Ejecuta git checkout '.$configured.' y alinea con origin antes de desplegar.';
    }

    /**
     * Resumen legible si el gate CI (SDD-100) bloquea este modo de deploy.
     *
     * @param  'auto'|'manual'  $mode
     */
    public function ciGateBlockSummary(string $mode): ?string
    {
        $remoteSha = null;
        if ((bool) config('centinela.deploy.require_ci_match_sha', false)) {
            $remoteSha = $this->fetchRemoteGitState($this->tryGitRevParse('HEAD'))['remote_sha'];
        }

        return app(GithubCiStatusService::class)->deployGateBlockReason($mode, $remoteSha);
    }

    /**
     * @return array{
     *     readable: bool,
     *     git_available: bool,
     *     abbrev_ref: string|null,
     *     detached: bool|null,
     *     head_sha: string|null,
     *     head_sha_short: string|null,
     *     deploy_branch: string,
     *     aligned: bool|null
     * }
     */
    public function gitCheckoutDiagnostic(): array
    {
        $deployBranch = $this->deployBranch();

        $diagnostic = [
            'readable' => $this->shouldInvokeGitForStatusRead(),
            'git_available' => false,
            'abbrev_ref' => null,
            'detached' => null,
            'head_sha' => null,
            'head_sha_short' => null,
            'deploy_branch' => $deployBranch,
            'aligned' => null,
        ];

        if (! $diagnostic['readable']) {
            return $diagnostic;
        }

        $headSha = $this->tryGitRevParse('HEAD');
        $diagnostic['head_sha'] = $headSha;
        $diagnostic['head_sha_short'] = is_string($headSha) && strlen($headSha) >= 7
            ? substr($headSha, 0, 7)
            : null;
        $diagnostic['git_available'] = is_string($headSha) && $headSha !== '';

        $abbrev = $this->currentCheckoutBranch();
        $diagnostic['abbrev_ref'] = $abbrev;

        if (! $diagnostic['git_available']) {
            $diagnostic['detached'] = null;
            $diagnostic['aligned'] = false;

            return $diagnostic;
        }

        $diagnostic['detached'] = $abbrev === null;
        $diagnostic['aligned'] = $abbrev === $deployBranch;

        return $diagnostic;
    }

    /**
     * Alinea el checkout a la rama de deploy antes de pull (solo CLI / worker con git legible).
     *
     * @return array{
     *     attempted: bool,
     *     recovered: bool,
     *     before: array<string, mixed>,
     *     after: array<string, mixed>,
     *     actions: list<string>,
     *     message: string,
     *     error: string|null
     * }
     */
    public function ensureCheckoutOnDeployBranch(): array
    {
        $before = $this->gitCheckoutDiagnostic();

        if (! $before['readable']) {
            return [
                'attempted' => false,
                'recovered' => true,
                'before' => $before,
                'after' => $before,
                'actions' => [],
                'message' => 'Validación Git diferida (HTTP); recuperación de rama en el worker CLI.',
                'error' => null,
            ];
        }

        if ($before['aligned'] === true) {
            return [
                'attempted' => false,
                'recovered' => true,
                'before' => $before,
                'after' => $before,
                'actions' => [],
                'message' => 'Checkout ya alineado en rama '.$before['deploy_branch'].'.',
                'error' => null,
            ];
        }

        $branch = $this->deployBranch();
        $remote = (string) config('centinela.deploy.remote', 'origin');
        $remoteRef = "{$remote}/{$branch}";
        $actions = [];

        try {
            $actions[] = "git fetch --prune {$remote} {$branch}";
            $this->fetchRemoteRefs($remote, $branch);

            try {
                $actions[] = "git checkout {$branch}";
                $this->runGit(['checkout', $branch], timeoutSeconds: 30);
            } catch (\Throwable $checkoutBranch) {
                $actions[] = "git checkout -B {$branch} {$remoteRef}";
                $this->runGit(['checkout', '-B', $branch, $remoteRef], timeoutSeconds: 30);
            }

            $after = $this->gitCheckoutDiagnostic();

            if ($after['aligned'] === true) {
                $message = 'Checkout recuperado en rama '.$branch;
                if (is_string($after['head_sha_short']) && $after['head_sha_short'] !== '') {
                    $message .= ' (HEAD '.$after['head_sha_short'].').';
                } else {
                    $message .= '.';
                }

                Log::info('centinela.deploy.checkout_recovered', [
                    'before' => $before,
                    'after' => $after,
                    'actions' => $actions,
                ]);

                return [
                    'attempted' => true,
                    'recovered' => true,
                    'before' => $before,
                    'after' => $after,
                    'actions' => $actions,
                    'message' => $message,
                    'error' => null,
                ];
            }

            $message = 'No se pudo alinear el checkout en la rama '.$branch.' tras fetch/checkout.';

            Log::warning('centinela.deploy.checkout_recovery_failed', [
                'before' => $before,
                'after' => $after,
                'actions' => $actions,
            ]);

            return [
                'attempted' => true,
                'recovered' => false,
                'before' => $before,
                'after' => $after,
                'actions' => $actions,
                'message' => $message,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('centinela.deploy.checkout_recovery_error', [
                'before' => $before,
                'actions' => $actions,
                'error' => $e->getMessage(),
            ]);

            return [
                'attempted' => true,
                'recovered' => false,
                'before' => $before,
                'after' => $this->gitCheckoutDiagnostic(),
                'actions' => $actions,
                'message' => 'Error al recuperar checkout en rama '.$branch.': '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Líneas de diagnóstico para consola (deploy:status, fallos manual/auto).
     *
     * @return list<string>
     */
    public function formatConsoleDiagnosticLines(): array
    {
        $status = $this->operationalStatus();
        $git = is_array($status['git_checkout'] ?? null) ? $status['git_checkout'] : [];
        $lines = [];

        if (($git['readable'] ?? false) === false) {
            $lines[] = 'Git en UI HTTP: validación diferida al worker CLI (git_status_via_http=false).';
        } elseif (($git['git_available'] ?? false) === false) {
            $lines[] = 'Git no responde en '.base_path().' (rev-parse HEAD falló).';
        } elseif (($git['detached'] ?? false) === true) {
            $lines[] = 'Estado: detached HEAD en commit '.($git['head_sha_short'] ?? '?').'.';
        } elseif (is_string($git['abbrev_ref'] ?? null) && $git['abbrev_ref'] !== '') {
            $lines[] = 'Rama actual: '.$git['abbrev_ref']
                .(is_string($git['head_sha_short'] ?? null) ? ' @ '.$git['head_sha_short'] : '').'.';
        }

        if (is_string($status['branch_mismatch'] ?? null) && $status['branch_mismatch'] !== '') {
            $lines[] = 'Bloqueo: '.$status['branch_mismatch'];
        }

        if (is_int($status['commits_ahead_on_remote'] ?? null)) {
            $lines[] = 'Commits por traer de origin/'.$status['deploy_branch'].': '.$status['commits_ahead_on_remote'].'.';
        }

        if (is_string($status['last_success_sha'] ?? null) && $status['last_success_sha'] !== '') {
            $lines[] = 'Último deploy exitoso (to_sha): '.substr($status['last_success_sha'], 0, 7).'.';
        }

        return $lines;
    }

    public function emitConsoleOutcome(Command $command, array $result): void
    {
        $command->line('');

        if ($result['ok']) {
            $command->info($result['summary']);
        } else {
            $command->error($result['summary']);

            foreach ($this->formatConsoleDiagnosticLines() as $line) {
                $command->line('  → '.$line);
            }
        }

        $executionId = $result['execution_id'] ?? null;
        if (is_int($executionId) && $executionId > 0) {
            $this->emitConsoleTraceabilityHints($command, $executionId);
        }
    }

    private function emitConsoleTraceabilityHints(Command $command, int $executionId): void
    {
        $artifactsRel = trim(
            str_replace('\\', '/', (string) config('centinela.deploy.artifacts_path', 'logs/deploy')),
            '/'
        );
        if ($artifactsRel === '') {
            $artifactsRel = 'logs/deploy';
        }

        $command->line('');
        $command->line('Dónde revisar #'.$executionId.':');
        $command->line('  → Panel Deploy');
        $command->line('  → Artefactos: storage/'.$artifactsRel.'/'.$executionId.'/');
        $command->line('  → Log: centinela.deploy.step');
    }

    /**
     * Evalúa si el deploy puede omitirse (sin backup ni git pull).
     *
     * @return array{
     *     skip: bool,
     *     local_sha: string|null,
     *     remote_sha: string|null,
     *     last_success_sha: string|null,
     *     commits_ahead_on_remote: int|null,
     *     has_pending_migrations: bool
     * }
     */
    public function assessIdempotentDeploy(): array
    {
        $localSha = $this->tryGitRevParse('HEAD');
        $remoteGit = $this->fetchRemoteGitState($localSha);
        $remoteSha = $remoteGit['remote_sha'];
        $commitsAheadOnRemote = $remoteGit['commits_ahead_on_remote'];
        $lastSuccessSha = $this->lastSuccessfulDeploySha();
        $hasPendingMigrations = $this->hasPendingMigrations();

        $skip = is_string($localSha)
            && is_string($lastSuccessSha)
            && $localSha === $lastSuccessSha
            && ! $hasPendingMigrations
            && $commitsAheadOnRemote === 0;

        return [
            'skip' => $skip,
            'local_sha' => $localSha,
            'remote_sha' => $remoteSha,
            'last_success_sha' => $lastSuccessSha,
            'commits_ahead_on_remote' => $commitsAheadOnRemote,
            'has_pending_migrations' => $hasPendingMigrations,
        ];
    }

    /**
     * @return array{
     *     automatic_enabled: bool,
     *     schedule_blocked: bool,
     *     deploy_branch: string,
     *     checkout_branch: string|null,
     *     branch_aligned: bool,
     *     branch_mismatch: string|null,
     *     local_sha: string|null,
     *     remote_sha: string|null,
     *     commits_ahead_on_remote: int|null,
     *     last_success_sha: string|null,
     *     has_pending_migrations: bool,
     *     would_skip: bool,
     *     auto_success_today: bool,
     *     last_execution: array{id: int, mode: string, status: string, summary: string|null, finished_at: string|null}|null
     * }
     */
    public function operationalStatus(): array
    {
        $assessment = $this->assessIdempotentDeploy();
        $last = DeployExecution::query()->orderByDesc('id')->first();

        $lastExecution = null;
        if ($last !== null) {
            $lastExecution = [
                'id' => $last->id,
                'mode' => (string) $last->mode,
                'status' => (string) $last->status,
                'summary' => $last->summary,
                'finished_at' => $last->finished_at?->toIso8601String(),
            ];
        }

        return [
            'automatic_enabled' => (bool) config('centinela.deploy.automatic_enabled', false),
            'schedule_blocked' => Cache::get(self::SCHEDULE_LOCK_KEY) === '1',
            'deploy_branch' => $this->deployBranch(),
            'checkout_branch' => $this->currentCheckoutBranch(),
            'git_checkout' => $this->gitCheckoutDiagnostic(),
            'branch_aligned' => $this->isCheckoutAlignedWithDeployBranch(),
            'branch_mismatch' => $this->branchMismatchSummary(),
            'local_sha' => $assessment['local_sha'],
            'remote_sha' => $assessment['remote_sha'],
            'commits_ahead_on_remote' => $assessment['commits_ahead_on_remote'],
            'last_success_sha' => $assessment['last_success_sha'],
            'has_pending_migrations' => $assessment['has_pending_migrations'],
            'would_skip' => $assessment['skip'],
            'auto_success_today' => Cache::get('centinela:deploy:auto:last_success_date:v1') === now()->toDateString(),
            'last_execution' => $lastExecution,
        ];
    }

    protected function lastSuccessfulDeploySha(): ?string
    {
        $sha = DeployExecution::query()
            ->where('status', 'succeeded')
            ->whereNotNull('to_sha')
            ->orderByDesc('id')
            ->value('to_sha');

        return is_string($sha) && $sha !== '' ? $sha : null;
    }

    /**
     * @return array{remote_sha: string|null, commits_ahead_on_remote: int|null}
     */
    protected function fetchRemoteGitState(?string $localSha): array
    {
        $remote = (string) config('centinela.deploy.remote', 'origin');
        $branch = (string) config('centinela.deploy.branch', 'main');
        $remoteRef = "{$remote}/{$branch}";

        try {
            $this->fetchRemoteRefs($remote, $branch);

            $remoteSha = $this->tryGitRevParse($remoteRef);
            $commitsAheadOnRemote = null;

            if (is_string($localSha) && $localSha !== '') {
                $process = $this->runGit(['rev-list', '--count', "{$localSha}..{$remoteRef}"], timeoutSeconds: 15);
                $commitsAheadOnRemote = (int) trim($process->getOutput());
            }

            return [
                'remote_sha' => $remoteSha,
                'commits_ahead_on_remote' => $commitsAheadOnRemote,
            ];
        } catch (\Throwable) {
            return [
                'remote_sha' => null,
                'commits_ahead_on_remote' => null,
            ];
        }
    }

    /**
     * @return list<string>
     */
    protected function migrationPaths(): array
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');

        return array_values(array_unique(array_merge(
            $migrator->paths(),
            [database_path('migrations')],
        )));
    }

    protected function hasPendingMigrations(): bool
    {
        try {
            /** @var Migrator $migrator */
            $migrator = app('migrator');

            $hasPending = false;

            $migrator->usingConnection($migrator->getConnection(), function () use ($migrator, &$hasPending): void {
                if (! $migrator->repositoryExists()) {
                    $hasPending = true;

                    return;
                }

                $files = $migrator->getMigrationFiles($this->migrationPaths());
                $ran = $migrator->getRepository()->getRan();

                foreach (array_keys($files) as $migrationName) {
                    if (! in_array($migrationName, $ran, true)) {
                        $hasPending = true;

                        return;
                    }
                }
            });

            return $hasPending;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @param  array{
     *     skip: bool,
     *     local_sha: string|null,
     *     remote_sha: string|null,
     *     last_success_sha: string|null,
     *     has_pending_migrations: bool
     * }  $assessment
     * @return array{ok: bool, execution_id: int|null, summary: string}
     */
    private function recordSkippedDeploy(
        string $mode,
        ?User $actor,
        array $assessment,
        ?DeployExecution $existing = null,
    ): array {
        $sha = $assessment['local_sha'];
        $summary = 'Deploy omitido: código al día con remoto y último deploy exitoso; sin migraciones pendientes.';

        $payload = [
            'mode' => $mode,
            'status' => 'skipped',
            'actor_user_id' => $actor?->id,
            'from_sha' => $sha,
            'to_sha' => $sha,
            'branch' => (string) config('centinela.deploy.branch', 'main'),
            'backup_status' => 'skipped',
            'migrate_status' => 'skipped',
            'rollback_status' => 'skipped',
            'summary' => $summary,
            'started_at' => now(),
            'finished_at' => now(),
        ];

        if ($existing instanceof DeployExecution) {
            $existing->fill($payload);
            $existing->meta = array_merge(is_array($existing->meta) ? $existing->meta : [], [
                'idempotent' => $assessment,
            ]);
            $existing->save();
            $this->notifyAdminOps($existing);

            return ['ok' => true, 'execution_id' => $existing->id, 'summary' => $summary];
        }

        $execution = DeployExecution::query()->create(array_merge($payload, [
            'meta' => ['idempotent' => $assessment],
        ]));
        $this->notifyAdminOps($execution);

        return ['ok' => true, 'execution_id' => $execution->id, 'summary' => $summary];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{ok: bool, execution_id: int, summary: string}
     */
    private function recordFailedPrecondition(
        string $mode,
        ?User $actor,
        string $summary,
        array $meta = [],
        bool $blockSchedule = false,
        ?DeployExecution $existing = null,
    ): array {
        $payload = [
            'mode' => $mode,
            'status' => 'failed_precondition',
            'actor_user_id' => $actor?->id,
            'branch' => $this->deployBranch(),
            'from_sha' => $this->tryGitRevParse('HEAD'),
            'backup_status' => ($meta['reason'] ?? '') === 'backup_failed' ? 'failed' : 'skipped',
            'migrate_status' => 'skipped',
            'rollback_status' => 'skipped',
            'summary' => $summary,
            'started_at' => now(),
            'finished_at' => now(),
        ];

        if ($existing instanceof DeployExecution) {
            $existing->fill($payload);
            $existing->meta = array_merge(is_array($existing->meta) ? $existing->meta : [], $meta);
            $existing->save();
            $execution = $existing;
        } else {
            $execution = DeployExecution::query()->create(array_merge($payload, [
                'meta' => $meta,
            ]));
        }

        $this->notifyAdminOps($execution);

        if ($blockSchedule) {
            $this->blockAutoSchedule();
        }

        return ['ok' => false, 'execution_id' => $execution->id, 'summary' => $summary];
    }

    /**
     * @return array{ok: bool, execution_id: int|null, summary: string}
     */
    private function run(string $mode, ?User $actor, ?DeployExecution $precreated = null): array
    {
        $this->refreshRuntimeConfiguration();

        $checkoutRecovery = null;
        if ($this->shouldInvokeGitForStatusRead()) {
            $checkoutRecovery = $this->ensureCheckoutOnDeployBranch();
            if ($checkoutRecovery['attempted']) {
                Log::info('centinela.deploy.checkout_recovery', [
                    'mode' => $mode,
                    'recovered' => $checkoutRecovery['recovered'],
                    'message' => $checkoutRecovery['message'],
                    'actions' => $checkoutRecovery['actions'],
                ]);
            }
        }

        $branchMismatch = $this->branchMismatchSummary();
        if ($branchMismatch !== null) {
            $meta = [
                'reason' => 'branch_mismatch',
                'git' => $this->gitCheckoutDiagnostic(),
            ];
            if ($checkoutRecovery !== null) {
                $meta['checkout_recovery'] = $checkoutRecovery;
            }

            Log::warning('centinela.deploy.branch_mismatch', [
                'mode' => $mode,
                'summary' => $branchMismatch,
                'meta' => $meta,
            ]);

            return $this->recordFailedPrecondition($mode, $actor, $branchMismatch, $meta, false, $precreated);
        }

        $ciGateSummary = $this->ciGateBlockSummary($mode);
        if ($ciGateSummary !== null) {
            Log::warning('centinela.deploy.ci_gate_blocked', [
                'mode' => $mode,
                'summary' => $ciGateSummary,
            ]);

            return $this->recordFailedPrecondition($mode, $actor, $ciGateSummary, [
                'reason' => 'ci_gate',
                'require_ci_mode' => app(GithubCiStatusService::class)->normalizedRequireCiMode(),
            ], false, $precreated);
        }

        $lock = Cache::lock('centinela:deploy:run:lock:v1', 600);
        if (! $lock->get()) {
            $summary = 'Otro deploy está en progreso.';
            if ($precreated instanceof DeployExecution) {
                return $this->recordFailedPrecondition($mode, $actor, $summary, [
                    'reason' => 'lock_busy',
                ], false, $precreated);
            }

            $this->logEarlyExit(
                reason: 'lock_busy',
                mode: $mode,
                ok: false,
                summary: $summary,
                level: 'warning',
            );

            return ['ok' => false, 'execution_id' => null, 'summary' => $summary];
        }

        $assessment = $this->assessIdempotentDeploy();

        if ($assessment['skip']) {
            try {
                return $this->recordSkippedDeploy($mode, $actor, $assessment, $precreated);
            } finally {
                optional($lock)->release();
            }
        }

        if ($precreated instanceof DeployExecution) {
            $execution = $precreated;
            $execution->status = 'running';
            $execution->actor_user_id = $actor?->id ?? $execution->actor_user_id;
            $execution->branch = (string) config('centinela.deploy.branch', 'main');
            $execution->started_at = now();
            $execution->finished_at = null;
            $execution->summary = null;
            $execution->save();
        } else {
            $execution = DeployExecution::query()->create([
                'mode' => $mode,
                'status' => 'running',
                'actor_user_id' => $actor?->id,
                'branch' => (string) config('centinela.deploy.branch', 'main'),
                'started_at' => now(),
            ]);
        }

        $changedCode = false;
        $ranMigrations = false;
        $fromSha = null;
        $toSha = null;
        $recorder = new DeployStepRecorder($execution, $this->consoleCommand);

        try {
            $fromSha = $this->tryGitRevParse('HEAD');
            $execution->from_sha = $fromSha;
            $execution->save();

            $backup = $recorder->record('backup', function () {
                return $this->runBackup();
            });
            $execution->backup_status = $backup['status'] ?? (($backup['ok'] ?? false) ? 'ok' : 'failed');
            $execution->save();

            if (! ($backup['ok'] ?? false)) {
                $execution->status = 'failed_precondition';
                $execution->summary = 'Backup falló; no se ejecutó deploy.';
                $execution->finished_at = now();
                $execution->meta = array_merge((array) ($execution->meta ?? []), [
                    'reason' => 'backup_failed',
                ]);
                $execution->save();
                $this->notifyAdminOps($execution);

                $this->blockAutoScheduleIfApplicable($mode);

                return ['ok' => false, 'execution_id' => $execution->id, 'summary' => (string) $execution->summary];
            }

            $gitOk = $recorder->record('git', function () {
                return $this->gitFastForward();
            });
            $changedCode = (bool) ($gitOk['changed'] ?? false);
            $toSha = $this->tryGitRevParse('HEAD');
            $execution->to_sha = $toSha;
            $execution->meta = array_merge((array) ($execution->meta ?? []), [
                'git' => $this->stageMetaWithoutOutput($gitOk),
            ]);
            $execution->save();

            if (! ($gitOk['ok'] ?? false)) {
                throw new \RuntimeException('Fallo git: '.DeployStepRecorder::scrub((string) ($gitOk['summary'] ?? 'error')));
            }

            $composerOk = $recorder->record('composer', function () use ($changedCode) {
                return $this->runComposerIfNeeded($changedCode);
            });
            $execution->meta = array_merge((array) ($execution->meta ?? []), [
                'composer' => $this->stageMetaWithoutOutput($composerOk),
            ]);
            $execution->save();

            if (! ($composerOk['ok'] ?? false)) {
                throw new \RuntimeException('Fallo composer: '.DeployStepRecorder::scrub((string) ($composerOk['summary'] ?? 'error')));
            }

            $livewireOk = $recorder->record('livewire_publish', function () use ($changedCode, $composerOk) {
                return $this->runLivewirePublishIfNeeded($changedCode, $composerOk);
            });
            $execution->meta = array_merge((array) ($execution->meta ?? []), [
                'livewire_publish' => $this->stageMetaWithoutOutput($livewireOk),
            ]);
            $execution->save();

            if (! ($livewireOk['ok'] ?? false)) {
                throw new \RuntimeException('Fallo livewire:publish: '.DeployStepRecorder::scrub((string) ($livewireOk['summary'] ?? 'error')));
            }

            $npmOk = $recorder->record('npm', function () use ($changedCode, $fromSha, $toSha) {
                return $this->runNpmBuildIfNeeded($changedCode, $fromSha, $toSha);
            });
            $execution->meta = array_merge((array) ($execution->meta ?? []), [
                'npm' => $this->stageMetaWithoutOutput($npmOk),
            ]);
            $execution->save();

            if (! ($npmOk['ok'] ?? false)) {
                throw new \RuntimeException('Fallo npm build: '.DeployStepRecorder::scrub((string) ($npmOk['summary'] ?? 'error')));
            }

            $migrateOk = $recorder->record('migrate', function () {
                return $this->runMigrate();
            });
            $ranMigrations = (bool) ($migrateOk['ran'] ?? false);
            $execution->migrate_status = ($migrateOk['ok'] ?? false) ? 'ok' : 'failed';
            $execution->meta = array_merge((array) ($execution->meta ?? []), [
                'migrate' => $this->stageMetaWithoutOutput($migrateOk),
            ]);
            $execution->save();

            if (! ($migrateOk['ok'] ?? false)) {
                throw new \RuntimeException('Fallo migración: '.DeployStepRecorder::scrub((string) ($migrateOk['summary'] ?? 'error')));
            }

            $postDeploy = $recorder->record('post_deploy', function () {
                return $this->runPostDeployVerification();
            });
            $execution->meta = array_merge((array) ($execution->meta ?? []), [
                'post_deploy' => $this->stageMetaWithoutOutput($postDeploy),
            ]);
            $execution->save();

            if (! ($postDeploy['ok'] ?? false)) {
                throw new \RuntimeException('Verificación post-deploy: '.DeployStepRecorder::scrub((string) ($postDeploy['summary'] ?? 'error')));
            }

            $deployMeta = [];
            $deployedVersion = $this->deployedVersionResolver()->resolveTagFromSha($toSha);
            if ($deployedVersion !== null) {
                $deployMeta['deployed_version'] = $deployedVersion;
                $deployMeta['deployed_at'] = now()->toIso8601String();
                $release = $this->githubReleasesService()->releaseForTag($deployedVersion);
                if ($release !== null && isset($release['id'])) {
                    $deployMeta['github_release_id'] = $release['id'];
                }
            }

            $execution->meta = array_merge((array) ($execution->meta ?? []), $deployMeta);
            $execution->status = 'succeeded';
            $execution->summary = 'Deploy exitoso.';
            $execution->finished_at = now();
            $execution->save();
            $this->notifyAdminOps($execution);

            Cache::forget(self::SCHEDULE_LOCK_KEY);

            return ['ok' => true, 'execution_id' => $execution->id, 'summary' => (string) $execution->summary];
        } catch (\Throwable $e) {
            Log::error('Deploy failed', [
                'execution_id' => $execution->id,
                'mode' => $mode,
                'error' => DeployStepRecorder::scrub($e->getMessage()),
            ]);

            $rollback = $recorder->record('rollback', function () use ($fromSha, $changedCode, $ranMigrations) {
                return $this->rollback(fromSha: $fromSha, codeChanged: $changedCode, ranMigrations: $ranMigrations);
            });
            $execution->rollback_status = ($rollback['ok'] ?? false) ? 'ok' : 'failed';
            $execution->meta = array_merge((array) ($execution->meta ?? []), [
                'rollback' => $this->stageMetaWithoutOutput($rollback),
                'error' => DeployStepRecorder::scrub($e->getMessage()),
            ]);
            $execution->status = 'failed';
            $execution->summary = 'Deploy falló: '.DeployStepRecorder::scrub($e->getMessage());
            $execution->finished_at = now();
            $execution->save();
            $this->notifyAdminOps($execution);

            $this->blockAutoScheduleIfApplicable($mode);

            return ['ok' => false, 'execution_id' => $execution->id, 'summary' => (string) $execution->summary];
        } finally {
            optional($lock)->release();
        }
    }

    private function notifyAdminOps(DeployExecution $execution): void
    {
        app(DeployAdminOpsNotifier::class)->notifyIfApplicable($execution->fresh() ?? $execution);
    }

    /**
     * Early-exit sin fila deploy_executions (SDD-238 / G5).
     *
     * @param  'info'|'warning'  $level
     */
    private function logEarlyExit(
        string $reason,
        string $mode,
        bool $ok,
        string $summary,
        string $level = 'info',
        ?int $executionId = null,
    ): void {
        $context = [
            'reason' => $reason,
            'mode' => $mode,
            'ok' => $ok,
            'summary' => $summary,
            'execution_id' => $executionId,
        ];

        if ($level === 'warning') {
            Log::warning('centinela.deploy.early_exit', $context);

            return;
        }

        Log::info('centinela.deploy.early_exit', $context);
    }

    /**
     * @param  array<string, mixed>  $stage
     * @return array<string, mixed>
     */
    private function stageMetaWithoutOutput(array $stage): array
    {
        unset($stage['output']);
        if (isset($stage['summary']) && is_string($stage['summary'])) {
            $stage['summary'] = DeployStepRecorder::scrub($stage['summary']);
        }

        return $stage;
    }

    /**
     * @return array{ok: bool, ran: bool, summary: string, output?: string|null}
     */
    private function runNpmBuildIfNeeded(bool $codeChanged, ?string $fromSha, ?string $toSha): array
    {
        if (! $codeChanged || ! (bool) config('centinela.deploy.run_npm_build_after_pull', false)) {
            return ['ok' => true, 'ran' => false, 'summary' => 'npm build omitido (sin cambio de código o deshabilitado).', 'output' => null, 'note' => 'omitido'];
        }

        if (! is_file(base_path('package.json'))) {
            return ['ok' => true, 'ran' => false, 'summary' => 'Sin package.json; npm omitido.', 'output' => null, 'note' => 'omitido'];
        }

        if (! $this->commitRangeTouchesFrontend($fromSha, $toSha)) {
            return ['ok' => true, 'ran' => false, 'summary' => 'Sin cambios de frontend en el rango desplegado.', 'output' => null, 'note' => 'omitido'];
        }

        try {
            $npmCi = Process::fromShellCommandline('npm ci --no-audit --no-fund', base_path());
            $npmCi->setTimeout(900);
            $npmCi->run();

            if (! $npmCi->isSuccessful()) {
                $out = trim($npmCi->getErrorOutput()."\n".$npmCi->getOutput());

                return [
                    'ok' => false,
                    'ran' => true,
                    'summary' => $out !== '' ? $out : 'npm ci falló',
                    'output' => $out !== '' ? $out : null,
                ];
            }

            $npmBuild = Process::fromShellCommandline('npm run build', base_path());
            $npmBuild->setTimeout(900);
            $npmBuild->run();

            if (! $npmBuild->isSuccessful()) {
                $out = trim($npmBuild->getErrorOutput()."\n".$npmBuild->getOutput());

                return [
                    'ok' => false,
                    'ran' => true,
                    'summary' => $out !== '' ? $out : 'npm run build falló',
                    'output' => $out !== '' ? $out : null,
                ];
            }

            $out = trim($npmCi->getOutput()."\n".$npmBuild->getOutput());

            return [
                'ok' => true,
                'ran' => true,
                'summary' => 'npm ci && npm run build OK',
                'output' => $out !== '' ? $out : 'npm ci && npm run build OK',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'ran' => true, 'summary' => $e->getMessage(), 'output' => $e->getMessage()];
        }
    }

    protected function commitRangeTouchesFrontend(?string $fromSha, ?string $toSha): bool
    {
        if (! is_string($fromSha) || ! is_string($toSha) || $fromSha === '' || $toSha === '' || $fromSha === $toSha) {
            return false;
        }

        try {
            $process = $this->runGit(['diff', '--name-only', $fromSha, $toSha], timeoutSeconds: 30);
            $lines = preg_split('/\R/', trim($process->getOutput())) ?: [];

            foreach ($lines as $line) {
                $path = trim((string) $line);
                if ($path !== '' && $this->pathAffectsFrontendBundle($path)) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return true;
        }
    }

    protected function pathAffectsFrontendBundle(string $path): bool
    {
        return (bool) preg_match(
            '#^(package\.json|package-lock\.json|vite\.config\.(js|ts)|resources/(js|sass)/|resources/css/|resources/.*\.(js|ts|vue|scss|css)|public/)#',
            $path
        );
    }

    /**
     * @return array{ok: bool, ran: bool, summary: string, checks: array<string, bool>, output?: string|null, note?: string|null}
     */
    private function runPostDeployVerification(): array
    {
        $checks = [];

        try {
            $checks['migrate_pending'] = ! $this->hasPendingMigrations();
            $about = $this->runArtisanCli('about', ['--only' => 'environment'], 120);
            $checks['artisan_about'] = $about['ok'];
            $aboutOut = $about['output'];

            $ok = ! in_array(false, $checks, true);
            $summary = $ok
                ? 'OK'
                : 'Falló: '.implode(', ', array_keys(array_filter($checks, fn (bool $v): bool => ! $v)));

            return [
                'ok' => $ok,
                'ran' => true,
                'summary' => $summary,
                'checks' => $checks,
                'output' => $aboutOut !== '' ? $aboutOut : $summary,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'ran' => true,
                'summary' => $e->getMessage(),
                'checks' => $checks,
                'output' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, ran: bool, summary: string, output?: string|null, note?: string|null}
     */
    private function runComposerIfNeeded(bool $codeChanged): array
    {
        if (! $codeChanged || ! (bool) config('centinela.deploy.run_composer_after_pull', false)) {
            return [
                'ok' => true,
                'ran' => false,
                'summary' => 'Composer omitido (sin cambio de código o deshabilitado).',
                'output' => null,
                'note' => 'omitido',
            ];
        }

        $result = $this->runComposerInstall();

        return [
            'ok' => $result['ok'],
            'ran' => true,
            'summary' => $result['ok']
                ? 'composer install OK'
                : ($result['output'] !== '' ? $result['output'] : 'composer install falló'),
            'output' => $result['output'] !== '' ? $result['output'] : ($result['ok'] ? 'composer install OK' : null),
        ];
    }

    /**
     * Composer install en subproceso (también usado en rollback tras git reset).
     *
     * @return array{ok: bool, exit_code: int, output: string}
     */
    protected function runComposerInstall(int $timeoutSeconds = 900): array
    {
        try {
            $process = Process::fromShellCommandline(
                'composer install --no-dev --optimize-autoloader --no-interaction',
                base_path()
            );
            $process->setTimeout($timeoutSeconds);
            $process->run();
            $out = trim($process->getErrorOutput()."\n".$process->getOutput());

            return [
                'ok' => $process->isSuccessful(),
                'exit_code' => (int) $process->getExitCode(),
                'output' => $out,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'exit_code' => 1,
                'output' => $e->getMessage(),
            ];
        }
    }

    /**
     * Artisan en proceso PHP fresco (SDD-280): evita Artisan::call tras composer en el mismo proceso.
     *
     * @param  array<string, scalar|null>  $arguments
     * @return array{ok: bool, exit_code: int, output: string}
     */
    protected function runArtisanCli(string $command, array $arguments = [], int $timeoutSeconds = 600): array
    {
        try {
            $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
            $argv = [$php, 'artisan', $command];

            foreach ($arguments as $key => $value) {
                if (is_int($key)) {
                    $argv[] = (string) $value;

                    continue;
                }

                if ($value === false || $value === null) {
                    continue;
                }

                if ($value === true) {
                    $argv[] = (string) $key;

                    continue;
                }

                $argv[] = (string) $key;
                $argv[] = (string) $value;
            }

            $process = new Process($argv, base_path());
            $process->setTimeout($timeoutSeconds);
            $process->run();
            $out = trim($process->getErrorOutput()."\n".$process->getOutput());

            return [
                'ok' => $process->isSuccessful(),
                'exit_code' => (int) $process->getExitCode(),
                'output' => $out,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'exit_code' => 1,
                'output' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, status: string, output?: string|null, note?: string|null}
     */
    private function runBackup(): array
    {
        if (app(DatabaseBackupSkipGuard::class)->shouldSkipScheduledBackup()) {
            return [
                'ok' => true,
                'status' => 'skipped',
                'output' => null,
                'note' => 'omitido (skip guard)',
            ];
        }

        $parameters = ['--skip-if-recent' => true];
        $email = app(ConfigurationService::class)->backupNotificationEmail();

        if ($email !== null) {
            $parameters['--email'] = $email;
        }

        $exit = Artisan::call('db:backup', $parameters);
        $out = trim(Artisan::output());

        return [
            'ok' => $exit === 0,
            'status' => $exit === 0 ? 'ok' : 'failed',
            'output' => $out !== '' ? $out : ($exit === 0 ? 'db:backup OK' : 'db:backup falló'),
        ];
    }

    private function blockAutoScheduleIfApplicable(string $mode): void
    {
        if ($mode === 'auto') {
            $this->blockAutoSchedule();
        }
    }

    private function blockAutoSchedule(): void
    {
        Cache::put(self::SCHEDULE_LOCK_KEY, '1', now()->addDays(30));
    }

    /**
     * Recarga settings Voyager → config() en procesos largos (p. ej. queue:work).
     */
    private function refreshRuntimeConfiguration(): void
    {
        $configuration = app(ConfigurationService::class);
        $configuration->invalidate();
        $configuration->overrideConfig();
    }

    /**
     * @return array{ok: bool, changed: bool, summary: string, output?: string|null, worktree_prep?: array{ok: bool, restored: list<string>, summary: string}}
     */
    private function gitFastForward(): array
    {
        $remote = (string) config('centinela.deploy.remote', 'origin');
        $branch = (string) config('centinela.deploy.branch', 'main');

        $before = $this->tryGitRevParse('HEAD');
        $worktreePrep = null;
        $pullOutput = '';

        try {
            $this->fetchRemoteRefs($remote, $branch);
            $worktreePrep = $this->prepareGitWorktreeBeforePull($remote, $branch);
            $pull = $this->runGit(['pull', '--ff-only', $remote, $branch], timeoutSeconds: 180);
            $pullOutput = trim($pull->getErrorOutput()."\n".$pull->getOutput());
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'changed' => false,
                'summary' => $e->getMessage(),
                'output' => $e->getMessage(),
            ];
        }

        $after = $this->tryGitRevParse('HEAD');
        $changed = is_string($before) && is_string($after) ? $before !== $after : false;
        $summaryLines = [
            'from='.($before ?? 'null'),
            'to='.($after ?? 'null'),
            'changed='.($changed ? 'yes' : 'no'),
        ];
        if ($pullOutput !== '') {
            $summaryLines[] = $pullOutput;
        }

        $result = [
            'ok' => true,
            'changed' => $changed,
            'summary' => 'OK',
            'output' => implode("\n", $summaryLines),
        ];

        if (is_array($worktreePrep)) {
            $result['worktree_prep'] = $worktreePrep;
        }

        return $result;
    }

    /**
     * @return array{ok: bool, restored: list<string>, summary: string}
     */
    protected function prepareGitWorktreeBeforePull(string $remote, string $branch): array
    {
        $configured = config('centinela.deploy.git_generated_paths_before_pull', []);
        if (! is_array($configured)) {
            $configured = [];
        }

        $guard = new DeployGitPullWorktreeGuard(
            fn (array $args, int $timeoutSeconds): Process => $this->runGit($args, $timeoutSeconds),
        );

        return $guard->prepare($remote, $branch, $configured);
    }

    /**
     * @param  array{ok: bool, ran: bool, summary: string}  $composerResult
     * @return array{ok: bool, ran: bool, summary: string, output?: string|null, note?: string|null}
     */
    private function runLivewirePublishIfNeeded(bool $codeChanged, array $composerResult): array
    {
        if (! (bool) config('centinela.deploy.run_livewire_publish_after_composer', true)) {
            return [
                'ok' => true,
                'ran' => false,
                'summary' => 'livewire:publish omitido (deshabilitado).',
                'output' => null,
                'note' => 'omitido',
            ];
        }

        if (! $codeChanged && ! ($composerResult['ran'] ?? false)) {
            return [
                'ok' => true,
                'ran' => false,
                'summary' => 'livewire:publish omitido (sin cambio de código ni composer).',
                'output' => null,
                'note' => 'omitido',
            ];
        }

        if (! class_exists(Livewire::class)) {
            return [
                'ok' => true,
                'ran' => false,
                'summary' => 'Livewire no instalado; livewire:publish omitido.',
                'output' => null,
                'note' => 'omitido',
            ];
        }

        try {
            $cli = $this->runArtisanCli('livewire:publish', [
                '--assets' => true,
                '--no-interaction' => true,
            ], 300);
            $out = $cli['output'];

            if (! $cli['ok']) {
                return [
                    'ok' => false,
                    'ran' => true,
                    'summary' => $out !== '' ? $out : 'livewire:publish --assets falló',
                    'output' => $out !== '' ? $out : null,
                ];
            }

            return [
                'ok' => true,
                'ran' => true,
                'summary' => 'livewire:publish --assets OK',
                'output' => $out !== '' ? $out : 'livewire:publish --assets OK',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'ran' => true, 'summary' => $e->getMessage(), 'output' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, ran: bool, summary: string, output?: string|null, note?: string|null}
     */
    private function runMigrate(): array
    {
        try {
            if (! $this->hasPendingMigrations()) {
                return [
                    'ok' => true,
                    'ran' => false,
                    'summary' => 'Sin migraciones pendientes.',
                    'output' => null,
                    'note' => 'omitido (sin pendientes)',
                ];
            }

            $cli = $this->runArtisanCli('migrate', ['--force' => true], 900);
            $out = $cli['output'];

            return [
                'ok' => $cli['ok'],
                'ran' => true,
                'summary' => $out !== '' ? $out : ($cli['ok'] ? 'OK' : 'Falló migrate'),
                'output' => $out !== '' ? $out : ($cli['ok'] ? 'migrate OK' : 'migrate falló'),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'ran' => false,
                'summary' => $e->getMessage(),
                'output' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, summary: string, actions: array<int, string>, output?: string|null}
     */
    private function rollback(?string $fromSha, bool $codeChanged, bool $ranMigrations): array
    {
        $actions = [];
        $chunks = [];

        try {
            if ($ranMigrations) {
                $actions[] = 'php artisan migrate:rollback --force';
                $rbMigrate = $this->runArtisanCli('migrate:rollback', ['--force' => true], 600);
                $chunks[] = $rbMigrate['output'];
                if (! $rbMigrate['ok']) {
                    $output = trim(implode("\n", array_filter($chunks, fn (string $c): bool => $c !== '')));

                    return [
                        'ok' => false,
                        'summary' => 'Rollback falló: migrate:rollback (CLI).',
                        'actions' => $actions,
                        'output' => $output !== '' ? $output : 'migrate:rollback falló',
                    ];
                }
            }

            if ($codeChanged && is_string($fromSha) && $fromSha !== '') {
                $actions[] = 'git reset --hard '.$fromSha;
                $proc = $this->runGit(['reset', '--hard', $fromSha], timeoutSeconds: 60);
                $chunks[] = trim($proc->getErrorOutput()."\n".$proc->getOutput());

                $actions[] = 'composer install --no-dev --optimize-autoloader --no-interaction';
                $composer = $this->runComposerInstall();
                $chunks[] = $composer['output'];
                if (! $composer['ok']) {
                    $output = trim(implode("\n", array_filter($chunks, fn (string $c): bool => $c !== '')));

                    return [
                        'ok' => false,
                        'summary' => 'Rollback falló: composer install tras git reset.',
                        'actions' => $actions,
                        'output' => $output !== '' ? $output : 'composer install falló',
                    ];
                }
            }

            $output = trim(implode("\n", array_filter($chunks, fn (string $c): bool => $c !== '')));

            return [
                'ok' => true,
                'summary' => 'Rollback ejecutado (mejor esfuerzo).',
                'actions' => $actions,
                'output' => $output !== '' ? $output : 'Rollback ejecutado (mejor esfuerzo).',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'summary' => 'Rollback falló: '.$e->getMessage(),
                'actions' => $actions,
                'output' => $e->getMessage(),
            ];
        }
    }

    protected function tryGitRevParse(string $ref): ?string
    {
        try {
            $proc = $this->runGit(['rev-parse', $ref], timeoutSeconds: 15);
            $out = trim($proc->getOutput());

            return $out !== '' ? $out : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function fetchRemoteRefs(string $remote, string $branch): void
    {
        $this->runGit(['fetch', '--prune', $remote, $branch], timeoutSeconds: 45);

        try {
            $this->runGit(['fetch', '--tags', $remote], timeoutSeconds: 60);
        } catch (\Throwable $e) {
            Log::warning('git fetch --tags failed; version resolution may be incomplete', [
                'remote' => $remote,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, string>  $args
     */
    protected function runGit(array $args, int $timeoutSeconds): Process
    {
        $gitBinary = (string) config('centinela.deploy.git_binary', 'git');
        $gitBinary = trim($gitBinary) !== '' ? trim($gitBinary) : 'git';

        $process = new Process(array_merge([$gitBinary], $args), base_path());
        $process->setTimeout($timeoutSeconds);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }
}
