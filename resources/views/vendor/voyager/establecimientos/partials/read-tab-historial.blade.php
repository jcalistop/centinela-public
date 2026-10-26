@php
    use App\Support\Cen\CenCatalogGlosaResolver;
    use App\Support\Cen\CenEstablishmentsUiCopy;

    $center = $dataTypeContent;
    $issuesCount = $centerSyncIssues?->total() ?? 0;
    $auditsCount = $centerAudits?->total() ?? 0;
    $fieldLabels = CenCatalogGlosaResolver::FIELD_LABELS;
@endphp
<div class="cen-read-detail">
    <div class="cen-read-spotlight">
        <p class="cen-read-spotlight__label">Trazabilidad del establecimiento</p>
        <p class="cen-read-spotlight__value">Código {{ $center->codigo }}</p>
        <p class="cen-read-spotlight__hint">
            Incidencias al sincronizar con el catálogo nacional y registro de cambios en Centinela.
        </p>
    </div>

    <div class="cen-read-section">
        <div class="cen-read-section__head">
            <i class="voyager-cloud-download"></i>
            <h4 class="cen-read-section__title">
                Sincronización nacional
                @if ($issuesCount > 0)
                    <span class="cen-read-count-badge">{{ $issuesCount }}</span>
                @endif
            </h4>
        </div>
        <p class="cen-read-intro" style="margin:8px;">
            Avisos registrados al actualizar desde datos.gob.cl que mencionan este establecimiento.
        </p>
        @if (empty($canViewHistorial) || $centerSyncIssues === null || $centerSyncIssues->isEmpty())
            <div class="cen-read-empty">
                <i class="voyager-cloud-download"></i>
                <p><strong>Sin incidencias de sincronización</strong></p>
                <p style="font-size:13px;max-width:400px;margin:0 auto;">
                    No hay advertencias ni errores de catálogo nacional asociados a este código.
                </p>
            </div>
        @else
            <div class="cen-read-table-wrap">
                <table class="table cen-read-data-table">
                    <thead>
                        <tr>
                            <th>Ejecución</th>
                            <th>Fecha</th>
                            <th>Alcance</th>
                            <th>Importancia</th>
                            <th>Motivo</th>
                            <th>Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($centerSyncIssues as $issue)
                            @php
                                $run = $issue->syncRun;
                                $scopeLabel =
                                    $run && $run->scope === 'full'
                                        ? 'Catálogo completo'
                                        : 'SS ' . ($run->ss_codigo ?? '—');
                                $severityClass = match ($issue->severity) {
                                    'error' => 'cen-read-severity--error',
                                    'warning' => 'cen-read-severity--warning',
                                    default => 'cen-read-severity--default',
                                };
                            @endphp
                            <tr>
                                <td><strong>#{{ $issue->cen_center_sync_run_id }}</strong></td>
                                <td>{{ $issue->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>{{ $scopeLabel }}</td>
                                <td>
                                    <span class="cen-read-severity {{ $severityClass }}">
                                        {{ CenEstablishmentsUiCopy::issueSeverityLabel($issue->severity) }}
                                    </span>
                                </td>
                                <td>{{ CenEstablishmentsUiCopy::issueCodeLabel($issue->code) }}</td>
                                <td>{{ CenEstablishmentsUiCopy::issueMessageForDisplay($issue, $fieldLabels) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="cen-read-section__foot">
                {{ $centerSyncIssues->appends(array_merge(request()->except('sync_issues_page'), ['tab' => 'historial']))->links() }}
            </div>
        @endif
    </div>

    <div class="cen-read-section">
        <div class="cen-read-section__head">
            <i class="voyager-eye"></i>
            <h4 class="cen-read-section__title">
                Historial de cambios en Centinela
                @if ($auditsCount > 0)
                    <span class="cen-read-count-badge">{{ $auditsCount }}</span>
                @endif
                @include('voyager::establecimientos.partials.help-icon', [
                    'text' => CenEstablishmentsUiCopy::HELP_AUDITORIA,
                ])
            </h4>
        </div>
        <p class="cen-read-intro" style="margin:8px;">
            Altas, modificaciones, bajas y restauraciones del ficha en Centinela (usuarios o sincronización).
        </p>
        @if ($centerAudits === null || $centerAudits->isEmpty())
            <div class="cen-read-empty">
                <i class="voyager-eye"></i>
                <p><strong>Sin historial de cambios</strong></p>
                <p style="font-size:13px;max-width:400px;margin:0 auto;">
                    No hay movimientos registrados para este establecimiento.
                </p>
            </div>
        @else
            <div class="cen-read-table-wrap">
                <table class="table cen-read-data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Acción</th>
                            <th>Usuario</th>
                            <th>Campos modificados</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($centerAudits as $audit)
                            @php
                                $old = is_array($audit->old_values) ? $audit->old_values : [];
                                $new = is_array($audit->new_values) ? $audit->new_values : [];
                                $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
                                $summary = collect($keys)
                                    ->take(6)
                                    ->map(function (string $key) use ($old, $new, $fieldLabels): string {
                                        $label = $fieldLabels[$key] ?? $key;
                                        $from = $old[$key] ?? '—';
                                        $to = $new[$key] ?? '—';
                                        if (is_array($from) || is_array($to)) {
                                            return $label . ': [varios valores]';
                                        }

                                        return $label . ': ' . $from . ' → ' . $to;
                                    })
                                    ->implode('; ');
                                $eventClass = match ($audit->event) {
                                    'created' => 'cen-read-event-badge--created',
                                    'updated' => 'cen-read-event-badge--updated',
                                    'deleted' => 'cen-read-event-badge--deleted',
                                    'restored' => 'cen-read-event-badge--restored',
                                    default => 'cen-read-event-badge--default',
                                };
                                $eventLabel = match ($audit->event) {
                                    'created' => 'Alta',
                                    'updated' => 'Modificación',
                                    'deleted' => 'Baja',
                                    'restored' => 'Restauración',
                                    default => $audit->event,
                                };
                            @endphp
                            <tr>
                                <td>{{ $audit->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>
                                    <span class="cen-read-event-badge {{ $eventClass }}">{{ $eventLabel }}</span>
                                </td>
                                <td>{{ $audit->user?->name ?? 'Sistema' }}</td>
                                <td><span class="cen-read-changes">{{ $summary !== '' ? $summary : '—' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="cen-read-section__foot">
                {{ $centerAudits->appends(array_merge(request()->except('audit_page'), ['tab' => 'historial']))->links() }}
            </div>
        @endif
    </div>
</div>
