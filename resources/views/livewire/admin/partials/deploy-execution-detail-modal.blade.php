@php
    /** @var \App\Models\DeployExecution $execution */
    $statusLabel = match ((string) $execution->status) {
        'succeeded' => 'success',
        'failed' => 'danger',
        'failed_precondition' => 'warning',
        'running' => 'info',
        'queued' => 'info',
        'skipped' => 'warning',
        default => 'default',
    };

    $stageLabel = static function (?string $status): string {
        return match ((string) $status) {
            'ok' => 'success',
            'failed' => 'danger',
            'skipped' => 'warning',
            default => 'default',
        };
    };

    $meta = is_array($execution->meta) ? $execution->meta : [];
    $durationSeconds = null;
    if ($execution->started_at && $execution->finished_at) {
        $durationSeconds = $execution->finished_at->diffInSeconds($execution->started_at);
    }
@endphp

<div class="modal fade in admin-dashboard-compass admin-dashboard-compass--modal"
    style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="margin: 80px auto;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" wire:click="closeExecutionDetail">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">
                    <i class="voyager-git"></i>
                    Deploy #{{ (int) $execution->id }}
                    <span class="label label-{{ $statusLabel }}" style="vertical-align: middle; margin-left: 6px;">
                        {{ strtoupper((string) $execution->status) }}
                    </span>
                </h4>
            </div>
            <div class="modal-body" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                @if (is_string($execution->summary) && $execution->summary !== '')
                    <div class="alert alert-{{ $statusLabel === 'default' ? 'info' : $statusLabel }}"
                        style="margin-top: 0;">
                        {{ $execution->summary }}
                    </div>
                @endif

                <table class="table table-bordered table-condensed" style="margin-bottom: 1.25rem;">
                    <tbody>
                        <tr>
                            <th style="width: 28%;">Modo</th>
                            <td>{{ strtoupper((string) $execution->mode) }}</td>
                        </tr>
                        <tr>
                            <th>Rama</th>
                            <td><code>{{ $execution->branch ?: '—' }}</code></td>
                        </tr>
                        <tr>
                            <th>Actor</th>
                            <td>
                                @if ($execution->actor)
                                    {{ $execution->actor->name }}
                                    <span class="text-muted">({{ $execution->actor->email }})</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Inicio</th>
                            <td>{{ $execution->started_at?->format('d-m-Y H:i:s') ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Fin</th>
                            <td>{{ $execution->finished_at?->format('d-m-Y H:i:s') ?? '—' }}</td>
                        </tr>
                        @if (!empty($meta['queue']) || !empty($meta['job_id']) || !empty($meta['dispatched_at']))
                            <tr>
                                <th>Cola / job</th>
                                <td>
                                    @if (!empty($meta['queue']))
                                        cola: <code>{{ $meta['queue'] }}</code>
                                    @endif
                                    @if (!empty($meta['job_id']))
                                        @if (!empty($meta['queue']))
                                            ·
                                        @endif
                                        job_id: <code>{{ $meta['job_id'] }}</code>
                                    @endif
                                    @if (!empty($meta['dispatched_at']))
                                        <div class="text-muted" style="margin-top: 4px;">
                                            Encolado: {{ $meta['dispatched_at'] }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <th>Duración</th>
                            <td>
                                @if ($durationSeconds !== null)
                                    {{ $durationSeconds }} s
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>SHA origen</th>
                            <td>
                                @if ($execution->from_sha)
                                    @php $fromUrl = $gitHubLinks->commitUrl((string) $execution->from_sha); @endphp
                                    @if ($fromUrl)
                                        <x-centinela.compass.external-link :href="$fromUrl" mono>
                                            <code style="word-break: break-all;">{{ $execution->from_sha }}</code>
                                        </x-centinela.compass.external-link>
                                    @else
                                        <code style="word-break: break-all;">{{ $execution->from_sha }}</code>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>SHA destino</th>
                            <td>
                                @if ($execution->to_sha)
                                    @php $toUrl = $gitHubLinks->commitUrl((string) $execution->to_sha); @endphp
                                    @if ($toUrl)
                                        <x-centinela.compass.external-link :href="$toUrl" mono>
                                            <code style="word-break: break-all;">{{ $execution->to_sha }}</code>
                                        </x-centinela.compass.external-link>
                                    @else
                                        <code style="word-break: break-all;">{{ $execution->to_sha }}</code>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>

                @php
                    $compareUrl = $gitHubLinks->compareUrl(
                        is_string($execution->from_sha) ? $execution->from_sha : null,
                        is_string($execution->to_sha) ? $execution->to_sha : null,
                    );
                @endphp
                @if ($compareUrl)
                    <p style="margin: 0 0 1.25rem;">
                        <x-centinela.compass.external-link :href="$compareUrl" class="btn btn-default btn-sm">
                            Ver diff en GitHub
                        </x-centinela.compass.external-link>
                    </p>
                @endif

                <h5 class="cen-modal-section-title">Etapas</h5>
                <table class="table table-bordered table-condensed" style="margin-bottom: 1.25rem;">
                    <thead>
                        <tr>
                            <th>Etapa</th>
                            <th style="width: 120px;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Backup</td>
                            <td>
                                <span class="label label-{{ $stageLabel($execution->backup_status) }}">
                                    {{ strtoupper((string) ($execution->backup_status ?: '—')) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Migraciones</td>
                            <td>
                                <span class="label label-{{ $stageLabel($execution->migrate_status) }}">
                                    {{ strtoupper((string) ($execution->migrate_status ?: '—')) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Rollback</td>
                            <td>
                                <span class="label label-{{ $stageLabel($execution->rollback_status) }}">
                                    {{ strtoupper((string) ($execution->rollback_status ?: '—')) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                @php
                    $steps = isset($meta['steps']) && is_array($meta['steps']) ? $meta['steps'] : [];
                    $stepStatusLabel = static function (?string $status): string {
                        return match ((string) $status) {
                            'finished' => 'success',
                            'failed' => 'danger',
                            'started' => 'info',
                            default => 'default',
                        };
                    };
                @endphp
                <h5 class="cen-modal-section-title">Timeline (meta.steps)</h5>
                @if ($steps === [])
                    <p class="text-muted" style="margin-bottom: 1.25rem;">Sin timeline tipada registrada.</p>
                @else
                    <table class="table table-bordered table-condensed" style="margin-bottom: 1.25rem;">
                        <thead>
                            <tr>
                                <th>Etapa</th>
                                <th style="width: 110px;">Estado</th>
                                <th style="width: 100px;">Duración</th>
                                <th>Artefacto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($steps as $step)
                                @php
                                    $stepName = is_array($step) ? (string) ($step['name'] ?? '—') : '—';
                                    $stepStatus = is_array($step) ? (string) ($step['status'] ?? '—') : '—';
                                    $durationMs =
                                        is_array($step) && isset($step['duration_ms'])
                                            ? (int) $step['duration_ms']
                                            : null;
                                    $artifact =
                                        is_array($step) && is_string($step['artifact'] ?? null)
                                            ? $step['artifact']
                                            : null;
                                    $note = is_array($step) && is_string($step['note'] ?? null) ? $step['note'] : null;
                                @endphp
                                <tr>
                                    <td><code>{{ $stepName }}</code></td>
                                    <td>
                                        <span class="label label-{{ $stepStatusLabel($stepStatus) }}">
                                            {{ strtoupper($stepStatus) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($durationMs !== null)
                                            {{ $durationMs }} ms
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if ($artifact)
                                            <code style="word-break: break-all;">{{ $artifact }}</code>
                                        @elseif ($note)
                                            <span class="text-muted">{{ $note }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if ($meta !== [])
                    <h5 class="cen-modal-section-title">Detalle técnico</h5>

                    @if (isset($meta['git']) && is_array($meta['git']))
                        <p style="margin-bottom: 0.35rem;"><strong>Git</strong></p>
                        <ul class="list-unstyled" style="margin-bottom: 0.75rem; padding-left: 1rem;">
                            <li>OK: {{ $meta['git']['ok'] ?? false ? 'sí' : 'no' }}</li>
                            <li>Código cambió: {{ $meta['git']['changed'] ?? false ? 'sí' : 'no' }}</li>
                            @if (!empty($meta['git']['summary']))
                                <li>Resumen: {{ $meta['git']['summary'] }}</li>
                            @endif
                        </ul>
                    @endif

                    @if (isset($meta['npm']) && is_array($meta['npm']))
                        <p style="margin-bottom: 0.35rem;"><strong>Frontend (npm)</strong></p>
                        <ul class="list-unstyled" style="margin-bottom: 0.75rem; padding-left: 1rem;">
                            <li>OK: {{ $meta['npm']['ok'] ?? false ? 'sí' : 'no' }}</li>
                            <li>Ejecutado: {{ $meta['npm']['ran'] ?? false ? 'sí' : 'no' }}</li>
                            @if (!empty($meta['npm']['summary']))
                                <li>Resumen: {{ $meta['npm']['summary'] }}</li>
                            @endif
                        </ul>
                    @endif

                    @if (isset($meta['post_deploy']) && is_array($meta['post_deploy']))
                        <p style="margin-bottom: 0.35rem;"><strong>Verificación post-deploy</strong></p>
                        <ul class="list-unstyled" style="margin-bottom: 0.75rem; padding-left: 1rem;">
                            <li>OK: {{ $meta['post_deploy']['ok'] ?? false ? 'sí' : 'no' }}</li>
                            @if (!empty($meta['post_deploy']['summary']))
                                <li>Resumen: {{ $meta['post_deploy']['summary'] }}</li>
                            @endif
                        </ul>
                    @endif

                    @if (isset($meta['composer']) && is_array($meta['composer']))
                        <p style="margin-bottom: 0.35rem;"><strong>Composer</strong></p>
                        <ul class="list-unstyled" style="margin-bottom: 0.75rem; padding-left: 1rem;">
                            <li>OK: {{ $meta['composer']['ok'] ?? false ? 'sí' : 'no' }}</li>
                            <li>Ejecutado: {{ $meta['composer']['ran'] ?? false ? 'sí' : 'no' }}</li>
                            @if (!empty($meta['composer']['summary']))
                                <li>Resumen: {{ $meta['composer']['summary'] }}</li>
                            @endif
                        </ul>
                    @endif

                    @if (isset($meta['migrate']) && is_array($meta['migrate']))
                        <p style="margin-bottom: 0.35rem;"><strong>Migraciones</strong></p>
                        <ul class="list-unstyled" style="margin-bottom: 0.75rem; padding-left: 1rem;">
                            <li>OK: {{ $meta['migrate']['ok'] ?? false ? 'sí' : 'no' }}</li>
                            <li>Ejecutadas: {{ $meta['migrate']['ran'] ?? false ? 'sí' : 'no' }}</li>
                            @if (!empty($meta['migrate']['summary']))
                                <li>Resumen: {{ $meta['migrate']['summary'] }}</li>
                            @endif
                        </ul>
                    @endif

                    @if (isset($meta['rollback']) && is_array($meta['rollback']))
                        <p style="margin-bottom: 0.35rem;"><strong>Rollback</strong></p>
                        <ul class="list-unstyled" style="margin-bottom: 0.75rem; padding-left: 1rem;">
                            <li>OK: {{ $meta['rollback']['ok'] ?? false ? 'sí' : 'no' }}</li>
                            @if (!empty($meta['rollback']['summary']))
                                <li>Resumen: {{ $meta['rollback']['summary'] }}</li>
                            @endif
                            @if (!empty($meta['rollback']['actions']) && is_array($meta['rollback']['actions']))
                                <li>Acciones:
                                    <ul>
                                        @foreach ($meta['rollback']['actions'] as $action)
                                            <li><code>{{ $action }}</code></li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endif
                        </ul>
                    @endif

                    @if (isset($meta['idempotent']) && is_array($meta['idempotent']))
                        <p style="margin-bottom: 0.35rem;"><strong>Evaluación idempotente</strong></p>
                        <ul class="list-unstyled" style="margin-bottom: 0.75rem; padding-left: 1rem;">
                            <li>Commits por traer del remoto:
                                {{ $meta['idempotent']['commits_ahead_on_remote'] ?? '—' }}</li>
                            <li>Migraciones pendientes:
                                {{ $meta['idempotent']['has_pending_migrations'] ?? false ? 'sí' : 'no' }}</li>
                        </ul>
                    @endif

                    @if (!empty($meta['error']))
                        <p style="margin-bottom: 0.35rem;"><strong>Error</strong></p>
                        <pre style="white-space: pre-wrap; margin-bottom: 0.75rem;">{{ $meta['error'] }}</pre>
                    @endif

                    <p style="margin-bottom: 0.35rem;"><strong>Meta (JSON)</strong></p>
                    <pre style="white-space: pre-wrap; max-height: 200px; overflow: auto; margin: 0;">{{ json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                @else
                    <p class="text-muted">Sin meta técnica registrada.</p>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" wire:click="closeExecutionDetail">Cerrar</button>
            </div>
        </div>
    </div>
</div>
