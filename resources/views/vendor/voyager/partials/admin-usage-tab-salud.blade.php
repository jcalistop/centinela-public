<div class="row">
    <div class="col-md-3">
        <x-centinela.compass.stat-tile icon="voyager-data" :value="strtoupper((string) ($systemHealth['queue']['connection'] ?? 'N/D'))" label="Queue driver" />
    </div>
    <div class="col-md-3">
        <x-centinela.compass.stat-tile icon="voyager-list" :value="is_numeric($systemHealth['worker']['pending_jobs'] ?? null)
            ? number_format((int) $systemHealth['worker']['pending_jobs'], 0, ',', '.')
            : 'N/D'" label="Jobs pendientes (cola)" />
    </div>
    <div class="col-md-3">
        <x-centinela.compass.stat-tile icon="voyager-warning" :value="is_numeric($systemHealth['worker']['failed_jobs_24h'] ?? null)
            ? number_format((int) $systemHealth['worker']['failed_jobs_24h'], 0, ',', '.')
            : 'N/D'" label="Failed jobs (24h)" />
    </div>
    <div class="col-md-3">
        <x-centinela.compass.stat-tile icon="voyager-watch" :value="is_numeric($systemHealth['scheduler']['minutes_ago'] ?? null)
            ? number_format((int) $systemHealth['scheduler']['minutes_ago'], 0, ',', '.')
            : 'N/D'" label="Min desde heartbeat scheduler" />
    </div>
</div>

<x-centinela.compass.section title="Estado" icon="voyager-check">
    <ul class="cen-bullet-list">
        <li>Scheduler: {{ strtoupper((string) ($systemHealth['scheduler']['status'] ?? 'unknown')) }}
            @if (isset($systemHealth['scheduler']['last_run']))
                <span class="text-muted">(último heartbeat: {{ $systemHealth['scheduler']['last_run'] }})</span>
            @endif
        </li>
        <li>Worker: {{ strtoupper((string) ($systemHealth['worker']['status'] ?? 'unknown')) }}</li>
        <li>Backup: {{ strtoupper((string) ($systemHealth['backup']['status'] ?? 'unknown')) }}</li>
        <li>Security: {{ strtoupper((string) ($systemHealth['security']['status'] ?? 'unknown')) }}</li>
    </ul>
</x-centinela.compass.section>

<x-centinela.compass.section title="Umbrales (config)" icon="voyager-params">
    <ul class="cen-bullet-list">
        <li>Heartbeat scheduler: alerta si pasan ≥ {{ (int) ($systemHealth['scheduler']['threshold_minutes'] ?? 0) }}
            min sin `schedule:heartbeat` (no confundir con jobs pendientes)</li>
        <li>Worker stale: {{ (int) ($systemHealth['worker']['stale_threshold_minutes'] ?? 0) }} min</li>
    </ul>
</x-centinela.compass.section>

@php
    $retention = is_array($retentionOps ?? null) ? $retentionOps : [];
@endphp
<x-centinela.compass.section title="Retención / limpiezas" icon="voyager-trash">
    @if (!empty($retention['error']))
        <p class="text-muted mb-2">{{ $retention['error'] }}</p>
    @endif
    <ul class="cen-bullet-list">
        <li>Failed jobs totales:
            {{ is_numeric($retention['failed_jobs_total'] ?? null)
                ? number_format((int) $retention['failed_jobs_total'], 0, ',', '.')
                : 'N/D' }}
            (retención prune: {{ (int) ($retention['failed_jobs_retention_hours'] ?? 168) }} h)
        </li>
        <li>Cierre tickets resueltos: {{ (int) ($retention['support_close_resolved_days'] ?? 5) }} días</li>
        <li>Logs scheduler (whitelist):
            @if (!empty($retention['schedule_logs']['error']))
                no disponible
            @else
                {{ number_format((int) ($retention['schedule_logs']['files'] ?? 0), 0, ',', '.') }} archivos,
                {{ number_format((int) round(((int) ($retention['schedule_logs']['bytes'] ?? 0)) / 1024), 0, ',', '.') }}
                KB
                @if (isset($retention['schedule_logs']['oldest_hours']))
                    <span class="text-muted">(más antiguo ~{{ (int) $retention['schedule_logs']['oldest_hours'] }}
                        h)</span>
                @endif
            @endif
        </li>
        <li>storage/temp:
            @if (!empty($retention['storage_temp']['error']))
                no disponible
            @else
                {{ number_format((int) ($retention['storage_temp']['files'] ?? 0), 0, ',', '.') }} archivos,
                {{ number_format((int) round(((int) ($retention['storage_temp']['bytes'] ?? 0)) / 1024), 0, ',', '.') }}
                KB
            @endif
        </li>
        <li>storage/app/temp:
            @if (!empty($retention['storage_app_temp']['error']))
                no disponible
            @else
                {{ number_format((int) ($retention['storage_app_temp']['files'] ?? 0), 0, ',', '.') }} archivos,
                {{ number_format((int) round(((int) ($retention['storage_app_temp']['bytes'] ?? 0)) / 1024), 0, ',', '.') }}
                KB
            @endif
        </li>
        <li class="text-muted">{{ $retention['runbook_hint'] ?? 'SDD-105' }}</li>
    </ul>
</x-centinela.compass.section>
