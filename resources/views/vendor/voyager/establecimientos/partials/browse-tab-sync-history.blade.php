@php
    use App\Support\Cen\CenEstablishmentsUiCopy;

    $indexUrl = \Illuminate\Support\Facades\Route::has('voyager.establecimientos.index')
        ? route('voyager.establecimientos.index')
        : url('/admin/establecimientos');
    $historialUrl = $indexUrl . (str_contains($indexUrl, '?') ? '&' : '?') . 'tab=historial';
    $runsCount = $syncRuns?->total() ?? 0;
    $syncRunQuery = filter_var(request()->query('sync_run'), FILTER_VALIDATE_INT);
@endphp

<div class="cen-read-detail">
    <div class="cen-read-spotlight">
        <p class="cen-read-spotlight__label">Sincronización nacional</p>
        <p class="cen-read-spotlight__value">
            Historial de ejecuciones
            @include('voyager::establecimientos.partials.help-icon', [
                'text' => CenEstablishmentsUiCopy::HELP_SYNC_HISTORY,
            ])
        </p>
        <p class="cen-read-spotlight__hint">
            Cada fila es una descarga desde <strong>datos.gob.cl</strong> (catálogo nacional de establecimientos).
            <strong>+ Creados</strong> nuevos en Centinela;
            <strong>~ Actualizados</strong> con datos refrescados;
            <strong>− Baja</strong> ocultados (cierre o ausencia en catálogo);
            <strong>Omit.</strong> no aplicados;
            <strong>Incidencias</strong> avisos de esa ejecución (histórico);
            <strong>Pend.</strong> textos del catálogo aún sin equivalencia (solo administrador).
        </p>
    </div>

    @if (is_int($syncRunQuery) && $syncRunQuery > 0 && empty($selectedSyncRun))
        <div class="cen-read-empty">
            <i class="voyager-warning"></i>
            <p><strong>Ejecución no disponible</strong></p>
            <p style="font-size:13px;max-width:400px;margin:0 auto;">
                La ejecución #{{ $syncRunQuery }} no existe o no tiene permiso para verla.
            </p>
            <p style="margin-top:12px;">
                <a href="{{ $historialUrl }}" class="btn btn-default btn-sm">
                    <i class="voyager-angle-left"></i> Volver al historial
                </a>
            </p>
        </div>
    @elseif (!empty($selectedSyncRun))
        @include('voyager::establecimientos.partials.browse-tab-sync-run-detail')
    @else
        <div class="cen-read-section">
            <div class="cen-read-section__head">
                <i class="voyager-cloud-download"></i>
                <h4 class="cen-read-section__title">
                    Ejecuciones registradas
                    @if ($runsCount > 0)
                        <span class="cen-read-count-badge">{{ $runsCount }}</span>
                    @endif
                </h4>
            </div>

            @if ($syncRuns === null || $syncRuns->isEmpty())
                <div class="cen-read-empty">
                    <i class="voyager-cloud-download"></i>
                    <p><strong>Sin sincronizaciones</strong></p>
                    <p style="font-size:13px;max-width:400px;margin:0 auto;">
                        Aún no se ha ejecutado una sincronización con el catálogo nacional. Use los botones
                        «Sincronizar catálogo» en la parte superior del listado.
                    </p>
                </div>
            @else
                <div class="cen-read-table-wrap">
                    <table class="table cen-read-data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Inicio</th>
                                <th>Fin</th>
                                <th>Alcance</th>
                                <th>Usuario</th>
                                <th>Estado</th>
                                <th class="text-right">
                                    + Creados
                                    @include('voyager::establecimientos.partials.help-icon', [
                                        'text' => CenEstablishmentsUiCopy::HELP_COLUMN_CREADOS,
                                    ])
                                </th>
                                <th class="text-right">
                                    ~ Actualiz.
                                    @include('voyager::establecimientos.partials.help-icon', [
                                        'text' => CenEstablishmentsUiCopy::HELP_COLUMN_ACTUALIZADOS,
                                    ])
                                </th>
                                <th class="text-right">
                                    − Baja
                                    @include('voyager::establecimientos.partials.help-icon', [
                                        'text' => CenEstablishmentsUiCopy::HELP_COLUMN_BAJA,
                                    ])
                                </th>
                                <th class="text-right" title="Registros no aplicados en esta ejecución">Omit.</th>
                                <th class="text-right">
                                    Incidencias
                                    @include('voyager::establecimientos.partials.help-icon', [
                                        'text' => CenEstablishmentsUiCopy::HELP_COLUMN_INCIDENCIAS,
                                    ])
                                </th>
                                <th class="text-right">
                                    Pend.
                                    @include('voyager::establecimientos.partials.help-icon', [
                                        'text' => CenEstablishmentsUiCopy::HELP_COLUMN_PENDIENTES,
                                    ])
                                </th>
                                <th>Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($syncRuns as $run)
                                @php
                                    $severityClass = match ($run->status) {
                                        'completed' => 'cen-read-severity--success',
                                        'failed' => 'cen-read-severity--error',
                                        'running' => 'cen-read-severity--warning',
                                        default => 'cen-read-severity--default',
                                    };
                                    $statusLabel = match ($run->status) {
                                        'completed' => 'Completada',
                                        'failed' => 'Fallida',
                                        'running' => 'En curso',
                                        default => $run->status,
                                    };
                                    $scopeLabel =
                                        $run->scope === 'full' ? 'Catálogo completo' : 'SS ' . ($run->ss_codigo ?? '—');
                                    $defaultChangeAction = \App\Enums\Cen\CenCenterSyncRunChangeAction::defaultForRun(
                                        $run,
                                    );
                                    $runDetailUrl =
                                        $indexUrl .
                                        (str_contains($indexUrl, '?') ? '&' : '?') .
                                        'tab=historial&sync_run=' .
                                        $run->id .
                                        '&run_panel=changes&change_action=' .
                                        $defaultChangeAction->value;
                                    $hasRunDetail =
                                        ($run->created_count ?? 0) > 0 ||
                                        ($run->updated_count ?? 0) > 0 ||
                                        ($run->soft_deleted_count ?? 0) > 0 ||
                                        ($run->skipped_count ?? 0) > 0 ||
                                        ($run->issues_count ?? 0) > 0;
                                @endphp
                                <tr>
                                    <td><strong>#{{ $run->id }}</strong></td>
                                    <td>{{ $run->started_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>{{ $run->finished_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>{{ $scopeLabel }}</td>
                                    <td>{{ $run->triggeredBy?->name ?? 'Sistema programado' }}</td>
                                    <td>
                                        <span class="cen-read-severity {{ $severityClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="text-right">{{ $run->created_count }}</td>
                                    <td class="text-right">{{ $run->updated_count }}</td>
                                    <td class="text-right">{{ $run->soft_deleted_count }}</td>
                                    <td class="text-right">{{ $run->skipped_count }}</td>
                                    <td class="text-right">{{ $run->issues_count }}</td>
                                    <td class="text-right">
                                        @if (($run->pending_unmapped_issues_count ?? 0) > 0)
                                            <strong>{{ $run->pending_unmapped_issues_count }}</strong>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($hasRunDetail)
                                            <a href="{{ $runDetailUrl }}" class="btn btn-sm btn-default">
                                                Ver detalle
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('voyager::establecimientos.partials.browse-voyager-pagination', [
                    'paginator' => $syncRuns,
                    'pageName' => 'sync_page',
                    'queryExcept' => ['tab' => 'historial'],
                ])
            @endif
        </div>
    @endif

    @if (empty($selectedSyncRun))
        @include('voyager::establecimientos.partials.browse-tab-glosa-equivalences')
    @endif
</div>
