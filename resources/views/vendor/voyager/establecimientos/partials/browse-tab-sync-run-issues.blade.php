@php
    use App\Support\Cen\CenEstablishmentsUiCopy;

    $issuesCount = $syncRunIssues?->total() ?? 0;
    $fieldLabels = $catalogFieldLabels ?? \App\Support\Cen\CenCatalogGlosaResolver::FIELD_LABELS;
@endphp

<h5 style="margin-top:0;">
    Incidencias
    @if ($issuesCount > 0)
        <span class="cen-read-count-badge">{{ $issuesCount }}</span>
    @endif
    @include('voyager::establecimientos.partials.help-icon', [
        'text' => CenEstablishmentsUiCopy::HELP_COLUMN_INCIDENCIAS,
    ])
</h5>

@if ($syncRunIssues === null || $syncRunIssues->isEmpty())
    <div class="cen-read-empty">
        <i class="voyager-check"></i>
        <p><strong>Sin incidencias registradas</strong></p>
        <p style="font-size:13px;max-width:480px;margin:0 auto;">
            Esta ejecución no registró advertencias ni errores por establecimiento.
        </p>
    </div>
@else
    <div class="cen-read-table-wrap">
        <table class="table cen-read-data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Establecimiento</th>
                    <th>Importancia</th>
                    <th>Motivo</th>
                    <th>Descripción</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($syncRunIssues as $issue)
                    @php
                        $severityClass = match ($issue->severity) {
                            'error' => 'cen-read-severity--error',
                            'warning' => 'cen-read-severity--warning',
                            default => 'cen-read-severity--default',
                        };
                    @endphp
                    <tr>
                        <td>{{ $issue->id }}</td>
                        <td>
                            @if (is_string($issue->establecimiento_codigo) && $issue->establecimiento_codigo !== '')
                                {{ $issue->establecimiento_codigo }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="cen-read-severity {{ $severityClass }}">
                                {{ CenEstablishmentsUiCopy::issueSeverityLabel($issue->severity) }}
                            </span>
                        </td>
                        <td>{{ CenEstablishmentsUiCopy::issueCodeLabel($issue->code) }}</td>
                        <td>{{ CenEstablishmentsUiCopy::issueMessageForDisplay($issue, $fieldLabels) }}</td>
                        <td>{{ $issue->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @include('voyager::establecimientos.partials.browse-voyager-pagination', [
        'paginator' => $syncRunIssues,
        'pageName' => 'run_issues_page',
        'queryExcept' => ['tab' => 'historial', 'sync_run' => $selectedSyncRun->id, 'run_panel' => 'issues'],
    ])
@endif
