<div wire:poll.10s="refreshDashboard">
    <x-centinela.compass.dashboard-block title="Deploy" icon="voyager-git">
        <x-slot name="actions">
            @if ($autoEnabled)
                <span class="label label-success">
                    Auto: ON
                </span>
                <span class="label label-{{ $autoBlocked ? 'danger' : 'success' }}">
                    Schedule: {{ $autoBlocked ? 'BLOQUEADO' : 'OK' }}
                </span>

                @if ($autoBlocked)
                    <button type="button" class="btn btn-warning btn-sm" wire:click="unlockAutoSchedule"
                        wire:confirm="¿Liberar el bloqueo del deploy automático? No ejecuta un deploy.">
                        Desbloquear auto
                    </button>
                @endif
            @else
                <span class="label label-info">
                    Modo: manual (cola releases)
                </span>
                @if ($pendingReleasesJobs > 0)
                    <span class="label label-warning">
                        {{ $pendingReleasesJobs }} job(s) pendiente(s)
                    </span>
                @endif
            @endif

            <button type="button" class="btn btn-primary btn-sm" wire:click="requestManualDeploy"
                wire:loading.attr="disabled" @disabled(!$branchAligned || $ciGateBlocksManual)
                wire:confirm="¿Está seguro de solicitar el deploy? Se encolará backup, actualización de código y migraciones si corresponde."
                @if (!$branchAligned) title="Alinea el checkout con la rama configurada antes de desplegar"
                @elseif ($ciGateBlocksManual)
                    title="{{ $ciGateBlockMessage }}"
                @else
                    title="Se procesa en la ventana diaria del servidor (cron_mail.bat) o vía CLI" @endif>
                <span wire:loading.remove wire:target="requestManualDeploy">
                    <i class="voyager-upload"></i> Solicitar deploy
                </span>
                <span wire:loading wire:target="requestManualDeploy">Evaluando…</span>
            </button>
        </x-slot>

        @if (is_string($branchMismatchMessage) && $branchMismatchMessage !== '')
            <div class="alert alert-danger">
                <strong>Rama de deploy desalineada.</strong>
                {{ $branchMismatchMessage }}
                @if (is_string($checkoutBranch) && $checkoutBranch !== '')
                    <br><small class="text-muted">Checkout: <code>{{ $checkoutBranch }}</code> · Configurado:
                        <code>{{ $deployBranch }}</code></small>
                @endif
            </div>
        @elseif (!$gitStatusViaHttp)
            <p class="cen-meta-line text-muted">
                La validación Git (rama, <code>git pull</code>) se ejecuta en el servidor al procesar la cola
                <code>releases</code> o el scheduler automático (usuario TS-Producción), no desde IIS.
            </p>
        @elseif (is_string($checkoutBranch) && $checkoutBranch !== '')
            <p class="cen-meta-line">
                Rama Git local: <code>{{ $checkoutBranch }}</code> (alineada con deploy)
            </p>
        @endif

        @if (is_string($feedbackMessage) && $feedbackMessage !== '')
            @php
                $feedbackAlertClass = match (true) {
                    $skipped => 'info',
                    $queued => 'success',
                    default => 'warning',
                };
            @endphp
            <div class="alert alert-{{ $feedbackAlertClass }} alert-dismissible">
                <button type="button" class="close" wire:click="dismissFeedback" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                {{ $feedbackMessage }}
            </div>
        @endif

        @if (is_string($queueError) && $queueError !== '')
            <div class="alert alert-warning alert-dismissible">
                <button type="button" class="close" wire:click="dismissFeedback" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                No se pudo encolar el deploy. {{ $queueError }}
            </div>
        @endif

        <div class="cen-status-stack">
            <div class="cen-status-row">
                <p class="cen-status-row__title">
                    <i class="voyager-github" aria-hidden="true"></i>
                    CI GitHub <code>{{ $deployBranch }}</code>
                </p>
                @php
                    $ciLabel = match ($ciStatus->state) {
                        'success' => 'success',
                        'failure' => 'danger',
                        'pending' => 'info',
                        default => 'default',
                    };
                @endphp
                <span class="label label-{{ $ciLabel }}">{{ strtoupper($ciStatus->state) }}</span>
                @if ($ciStatus->workflowName)
                    <span class="text-muted">{{ $ciStatus->workflowName }}</span>
                @endif
                @if ($ciStatus->headShaShort)
                    <code>{{ $ciStatus->headShaShort }}</code>
                @endif
                @if ($ciStatus->runAt)
                    <span class="text-muted">{{ $ciStatus->runAt->format('d-m-Y H:i') }}</span>
                @endif
                @if ($ciStatus->stale)
                    <span class="label label-warning">CACHÉ</span>
                @endif
                @if ($ciStatus->htmlUrl)
                    <a href="{{ $ciStatus->htmlUrl }}" target="_blank" rel="noopener noreferrer"
                        class="btn btn-default btn-xs cen-link cen-link--external">
                        Ver en GitHub
                        <i class="voyager-external" aria-hidden="true"></i>
                    </a>
                @endif
                @if (is_string($ciStatus->errorMessage) && $ciStatus->errorMessage !== '')
                    <p class="cen-meta-line" style="width: 100%; margin-bottom: 0;">{{ $ciStatus->errorMessage }}</p>
                @endif
                @if ($ciGateMode !== 'none')
                    <p class="cen-meta-line" style="width: 100%; margin-bottom: 0;">
                        Gate CI: <code>{{ $ciGateMode }}</code>
                        @if ($ciGateBlocksManual && is_string($ciGateBlockMessage))
                            — <span class="text-danger">{{ $ciGateBlockMessage }}</span>
                        @endif
                    </p>
                @endif
            </div>

            <div class="cen-status-row">
                <p class="cen-status-row__title">
                    <i class="voyager-tag" aria-hidden="true"></i>
                    Release GitHub <code>{{ $gitHubLinks->repository() }}</code>
                </p>
                @if (is_string($latestGithubRelease->tag) && $latestGithubRelease->tag !== '')
                    <span class="label label-primary">{{ $latestGithubRelease->tag }}</span>
                    @if (is_string($latestGithubRelease->name) && $latestGithubRelease->name !== '')
                        <span class="text-muted">{{ $latestGithubRelease->name }}</span>
                    @endif
                    @if ($latestGithubRelease->publishedAt)
                        <span
                            class="text-muted">{{ $latestGithubRelease->publishedAt->timezone(config('app.timezone'))->format('d-m-Y H:i') }}</span>
                    @endif
                    @if ($latestGithubRelease->isAheadOf($deployedVersion))
                        <span class="label label-warning"
                            title="Hay una versión publicada en GitHub más reciente que la desplegada">PENDIENTE
                            DEPLOY</span>
                    @elseif (is_string($deployedVersion) && $deployedVersion !== '')
                        <span class="label label-success">AL DÍA</span>
                    @endif
                    @if ($latestGithubRelease->stale)
                        <span class="label label-warning">CACHÉ</span>
                    @endif
                    @if ($latestGithubRelease->htmlUrl)
                        <a href="{{ $latestGithubRelease->htmlUrl }}" target="_blank" rel="noopener noreferrer"
                            class="btn btn-default btn-xs cen-link cen-link--external">
                            Ver release
                            <i class="voyager-external" aria-hidden="true"></i>
                        </a>
                    @endif
                    <a href="{{ $gitHubLinks->releasesIndexUrl() }}" target="_blank" rel="noopener noreferrer"
                        class="btn btn-default btn-xs cen-link cen-link--external">
                        Todos los releases
                        <i class="voyager-external" aria-hidden="true"></i>
                    </a>
                @else
                    <span class="label label-default">N/D</span>
                    @if ($latestGithubRelease->stale)
                        <span class="label label-warning">CACHÉ</span>
                    @endif
                    @if (is_string($latestGithubRelease->errorMessage) && $latestGithubRelease->errorMessage !== '')
                        <span class="text-muted">{{ $latestGithubRelease->errorMessage }}</span>
                    @endif
                    <a href="{{ $gitHubLinks->releasesIndexUrl() }}" target="_blank" rel="noopener noreferrer"
                        class="btn btn-default btn-xs cen-link cen-link--external">
                        Ver en GitHub
                        <i class="voyager-external" aria-hidden="true"></i>
                    </a>
                @endif
                @if ($latestGithubRelease->syncedAt)
                    <p class="cen-meta-line" style="width: 100%; margin-bottom: 0;">
                        Última sincronización:
                        {{ $latestGithubRelease->syncedAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                    </p>
                @endif
            </div>
        </div>

        <x-centinela.compass.section title="Comunicación de versión" icon="voyager-mail">
            <p class="cen-meta-line">
                Versión en producción:
                @if (is_string($deployedVersion) && $deployedVersion !== '')
                    <strong><code>{{ $deployedVersion }}</code></strong>
                    @if ($deployedAt)
                        <span class="text-muted">(deploy
                            {{ $deployedAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }})</span>
                    @endif
                @else
                    <span class="text-muted">Sin registro de deploy exitoso</span>
                @endif
            </p>
            <p class="cen-meta-line">
                Última notificación a control-gestión:
                @if (is_string($notifiedVersion) && $notifiedVersion !== '')
                    <code>{{ $notifiedVersion }}</code>
                @else
                    <span class="text-muted">Ninguna</span>
                @endif
                @if ($pendingCgNotification)
                    <span class="label label-warning">Pendiente comunicar</span>
                @endif
            </p>
            @if (!$githubReleasesReachable)
                <div class="alert alert-warning" style="margin-bottom: 0.75rem;">
                    Sin conectividad a GitHub Releases en este momento.
                    @if ($lastReleasesSyncAt)
                        Última sincronización conocida:
                        {{ $lastReleasesSyncAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
                    @endif
                </div>
            @endif
            <div class="btn-group" style="margin-bottom: 0.5rem;">
                <button type="button" class="btn btn-default btn-sm" wire:click="sendTestReleaseMail"
                    wire:loading.attr="disabled" @disabled(!is_string($deployedVersion) || $deployedVersion === '')
                    wire:confirm="¿Enviar correo de prueba a los administradores?">
                    Enviar correo de prueba
                </button>
                <button type="button" class="btn btn-primary btn-sm" wire:click="notifyControlGestion"
                    wire:loading.attr="disabled" @disabled(!$canNotifyOfficial)
                    wire:confirm="¿Notificar por correo a control-gestión de los SS habilitados?">
                    Notificar control-gestión
                </button>
            </div>
            @if (is_string($notifiedVersion) && is_string($deployedVersion) && $notifiedVersion === $deployedVersion)
                <label class="checkbox-inline" style="display: block; margin-top: 0.25rem;">
                    <input type="checkbox" wire:model.live="confirmResendNotify"> Reenviar aunque ya se notificó esta
                    versión
                </label>
            @endif
            @if (!$canNotifyOfficial && is_string($deployedVersion) && $deployedVersion !== '')
                <p class="text-muted cen-meta-line" style="margin-bottom: 0;">
                    El envío oficial requiere notas de versión disponibles para <code>{{ $deployedVersion }}</code>
                    (sincronice releases si aplica).
                </p>
            @endif
        </x-centinela.compass.section>

        <div class="row">
            <div class="col-md-6">
                <x-centinela.compass.section title="Últimos deploys" icon="voyager-list">
                    <div class="table-responsive">
                        <table class="table table-hover cen-compass-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Modo</th>
                                    <th>Estado</th>
                                    <th>Versión</th>
                                    <th>SHA</th>
                                    <th class="text-right">Inicio</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($executions as $e)
                                    <tr wire:key="deploy-exec-{{ $e->id }}"
                                        class="cen-compass-table__row--clickable" role="button"
                                        wire:click="showExecutionDetail({{ (int) $e->id }})"
                                        title="Ver detalle de la corrida">
                                        <td><code>{{ (int) $e->id }}</code></td>
                                        <td>
                                            @php
                                                $mode = (string) $e->mode;
                                                $modeLabel = \App\Support\Deploy\DeployExecutionMode::label($mode);
                                                $modeBadge = \App\Support\Deploy\DeployExecutionMode::isNotificationMode(
                                                    $mode,
                                                )
                                                    ? 'info'
                                                    : 'primary';
                                            @endphp
                                            <span class="label label-{{ $modeBadge }}"
                                                title="{{ strtoupper($mode) }}">{{ $modeLabel }}</span>
                                        </td>
                                        <td>
                                            @php
                                                $label = match ((string) $e->status) {
                                                    'succeeded' => 'success',
                                                    'failed' => 'danger',
                                                    'failed_precondition' => 'warning',
                                                    'running' => 'info',
                                                    'queued' => 'info',
                                                    'skipped' => 'warning',
                                                    default => 'default',
                                                };
                                            @endphp
                                            <span
                                                class="label label-{{ $label }}">{{ strtoupper((string) $e->status) }}</span>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            @php
                                                $executionVersion = $e->displayVersion();
                                            @endphp
                                            @if ($executionVersion !== null)
                                                <code>{{ $executionVersion }}</code>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td style="white-space:nowrap;">
                                            @php
                                                $shaShort =
                                                    is_string($e->to_sha) && $e->to_sha !== ''
                                                        ? substr($e->to_sha, 0, 7)
                                                        : null;
                                                $commitUrl =
                                                    $shaShort !== null
                                                        ? $gitHubLinks->commitUrl((string) $e->to_sha)
                                                        : null;
                                            @endphp
                                            @if ($shaShort !== null)
                                                @if ($commitUrl)
                                                    <x-centinela.compass.external-link :href="$commitUrl" mono
                                                        wire:click.stop title="Ver commit en GitHub">
                                                        <code>{{ $shaShort }}</code>
                                                    </x-centinela.compass.external-link>
                                                @else
                                                    <code>{{ $shaShort }}</code>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-right" style="white-space:nowrap;">
                                            {{ $e->started_at ? $e->started_at->format('d-m-Y H:i') : '' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-muted text-center" style="padding: 1.25rem;">
                                            Sin ejecuciones registradas.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-centinela.compass.section>
            </div>

            <div class="col-md-6">
                <x-centinela.compass.section title="Consola" icon="voyager-terminal">
                    <p class="text-muted" style="margin-bottom: 0.75rem; font-size: 13px;">
                        El orquestador registra cada ejecución en el historial (backup → git → composer/npm si aplica →
                        migraciones; rollback en fallo).
                        Desde la UI, <strong>Solicitar deploy</strong> encola un job en la cola <code>releases</code>
                        (procesada en la ventana diaria del servidor vía <code>cron_mail.bat</code> o
                        <code>queue:work --queue=releases</code> en consola).
                    </p>

                    <p class="text-muted" style="margin-bottom: 0.35rem; font-size: 12px; font-weight: 600;">
                        Deploy atómico (recomendado)
                    </p>
                    <pre class="cen-console-pre"># Estado operativo (rama, git, bloqueos, última ejecución)
php artisan centinela:deploy:status

# Deploy manual síncrono (mismo flujo que el botón; preferir --email)
php artisan centinela:deploy:manual --email={{ $actorUserEmail ?? 'admin@ejemplo.cl' }}
# Compat: --user_id={{ $actorUserId ?? '&lt;id_admin&gt;' }}

@if ($autoEnabled)
# Tras fallo del automático: liberar bloqueo
php artisan centinela:deploy:unlock

# Automático (scheduler; requiere Auto ON y no bloqueado)
php artisan centinela:deploy:auto
@else
# Cola releases (ventana diaria del servidor)
php artisan queue:work --queue=releases --stop-when-empty --max-time=1800
@endif

# Purgar historial antiguo (retención {{ $deployExecutionsRetentionDays }} días)
php artisan centinela:deploy:prune-executions</pre>

                    <p class="text-muted" style="margin: 0.75rem 0 0.35rem; font-size: 12px; font-weight: 600;">
                        Pasos manuales (alternativa; sin orquestador)
                    </p>
                    <pre class="cen-console-pre"># 1) Backup (obligatorio)
php artisan db:backup

# 2) Actualizar código (rama: {{ $deployBranch }})
git fetch --prune origin {{ $deployBranch }}
git pull --ff-only origin {{ $deployBranch }}

# 3) Dependencias (producción)
composer install --no-dev --optimize-autoloader

# 4) Migraciones
php artisan migrate --force

# 5) Front (si aplica)
npm ci &amp;&amp; npm run build

# 6) Limpieza caches
php artisan optimize:clear</pre>

                    <ul class="text-muted" style="margin: 0.75rem 0 0; padding-left: 1.25rem; font-size: 12px;">
                        <li>Antes de desplegar: checkout en <code>{{ $deployBranch }}</code> y CI en verde
                            (recomendado).</li>
                        <li>No mezclar pasos manuales con un deploy del orquestador en curso.</li>
                        @if ($autoEnabled)
                            <li>Logs deploy automático: <code>storage/logs/centinela-deploy-auto.log</code></li>
                        @else
                            <li>Logs cola releases: <code>storage/logs/queue-releases.log</code></li>
                        @endif
                    </ul>
                </x-centinela.compass.section>
            </div>
        </div>
    </x-centinela.compass.dashboard-block>

    @if ($selectedExecution)
        @include('livewire.admin.partials.deploy-execution-detail-modal', [
            'execution' => $selectedExecution,
            'gitHubLinks' => $gitHubLinks,
        ])
    @endif
</div>
