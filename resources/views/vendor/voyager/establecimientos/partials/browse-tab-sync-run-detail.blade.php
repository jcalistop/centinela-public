@php
    use App\Enums\Cen\CenCenterSyncRunChangeAction;
    use App\Support\Cen\CenCatalogGlosaResolver;
    use App\Support\Cen\CenEstablishmentsUiCopy;

    $indexUrl = \Illuminate\Support\Facades\Route::has('voyager.establecimientos.index')
        ? route('voyager.establecimientos.index')
        : url('/admin/establecimientos');
    $historialUrl = $indexUrl . (str_contains($indexUrl, '?') ? '&' : '?') . 'tab=historial';
    $run = $selectedSyncRun;
    $scopeLabel = $run->scope === 'full' ? 'Catálogo completo' : 'SS ' . ($run->ss_codigo ?? '—');
    $statusLabel = match ($run->status) {
        'completed' => 'Completada',
        'failed' => 'Fallida',
        'running' => 'En curso',
        default => $run->status,
    };
    $runBaseQuery = 'tab=historial&sync_run=' . $run->id;
    $changeTabs = [
        CenCenterSyncRunChangeAction::Created,
        CenCenterSyncRunChangeAction::Updated,
        CenCenterSyncRunChangeAction::SoftDeleted,
        CenCenterSyncRunChangeAction::Skipped,
    ];
    $changeFieldLabels = CenCatalogGlosaResolver::FIELD_LABELS;
@endphp

<div class="cen-read-section">
    <div class="cen-read-section__head cen-read-section__head--with-actions">
        <div class="cen-read-section__head-main">
            <i class="voyager-list"></i>
            <h4 class="cen-read-section__title">Detalle de la ejecución #{{ $run->id }}</h4>
        </div>
        <div class="pull-right">
            <a href="{{ $historialUrl }}" class="btn btn-default btn-sm">
                <i class="voyager-angle-left"></i> Volver al historial
            </a>
        </div>
    </div>

    <p class="cen-read-intro" style="margin:8px 12px;">
        {{ $scopeLabel }} · {{ $run->started_at?->format('d/m/Y H:i') ?? '—' }}
        · {{ $statusLabel }}
        · {{ $run->triggeredBy?->name ?? 'Sistema programado' }}
    </p>

    <ul class="nav nav-pills cen-sync-run-detail-tabs" style="margin:12px 12px 0;">
        @foreach ($changeTabs as $tabAction)
            @php
                $tabCount = $tabAction->countForRun($run);
                $tabUrl =
                    $indexUrl .
                    (str_contains($indexUrl, '?') ? '&' : '?') .
                    $runBaseQuery .
                    '&run_panel=changes&change_action=' .
                    $tabAction->value;
                $isActive = ($syncRunPanel ?? 'changes') === 'changes' && ($activeChangeAction ?? null) === $tabAction;
            @endphp
            <li class="{{ $isActive ? 'active' : '' }}">
                <a href="{{ $tabUrl }}">
                    {{ $tabAction->label() }}
                    <span class="badge">{{ $tabCount }}</span>
                </a>
            </li>
        @endforeach
        @if (CenCenterSyncRunChangeAction::Restored->countForRun($run) > 0)
            @php
                $restoredUrl =
                    $indexUrl .
                    (str_contains($indexUrl, '?') ? '&' : '?') .
                    $runBaseQuery .
                    '&run_panel=changes&change_action=restored';
                $restoredActive =
                    ($syncRunPanel ?? '') === 'changes' && ($activeChangeAction?->value ?? '') === 'restored';
            @endphp
            <li class="{{ $restoredActive ? 'active' : '' }}">
                <a href="{{ $restoredUrl }}">
                    Restaurados
                    <span class="badge">{{ CenCenterSyncRunChangeAction::Restored->countForRun($run) }}</span>
                </a>
            </li>
        @endif
        <li class="{{ ($syncRunPanel ?? '') === 'issues' ? 'active' : '' }}">
            <a
                href="{{ $indexUrl . (str_contains($indexUrl, '?') ? '&' : '?') . $runBaseQuery . '&run_panel=issues' }}">
                Incidencias
                <span class="badge">{{ $run->issues_count ?? 0 }}</span>
            </a>
        </li>
    </ul>

    <div class="cen-sync-run-detail-panel" style="padding:12px;">
        @if (($syncRunPanel ?? 'changes') === 'issues')
            @include('voyager::establecimientos.partials.browse-tab-sync-run-issues')
        @else
            @php
                $runCategoryTotal = $activeChangeAction?->countForRun($run) ?? 0;
                $listedChangesTotal = $syncRunChanges?->total() ?? 0;
            @endphp
            <h5 style="margin-top:0;">
                {{ $activeChangeAction?->label() ?? 'Establecimientos' }}
                @if ($runCategoryTotal > 0)
                    <span class="cen-read-count-badge">{{ $runCategoryTotal }}</span>
                @endif
            </h5>
            @if ($syncRunChanges === null || $syncRunChanges->isEmpty())
                <div class="cen-read-empty">
                    <i class="voyager-check"></i>
                    @if ($runCategoryTotal > 0)
                        <p><strong>Totales sin listado por establecimiento</strong></p>
                        <p style="font-size:13px;max-width:520px;margin:0 auto;">
                            Esta ejecución registró
                            <strong>{{ $runCategoryTotal }}</strong>
                            {{ strtolower($activeChangeAction?->label() ?? 'cambios') }}, pero no hay detalle
                            guardado (suele ocurrir en ejecuciones anteriores a esta mejora). Ejecute una nueva
                            sincronización para ver el listado con campos modificados.
                        </p>
                    @else
                        <p><strong>Sin registros en esta categoría</strong></p>
                        <p style="font-size:13px;max-width:480px;margin:0 auto;">
                            No hay establecimientos
                            <strong>{{ strtolower($activeChangeAction?->label() ?? 'cambio') }}</strong>
                            en esta ejecución.
                        </p>
                    @endif
                </div>
            @else
                <div class="cen-read-table-wrap">
                    <table class="table cen-read-data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Detalle</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($syncRunChanges as $change)
                                <tr>
                                    <td>{{ $change->id }}</td>
                                    <td>
                                        @if (is_string($change->establecimiento_codigo) && $change->establecimiento_codigo !== '')
                                            {{ $change->establecimiento_codigo }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $change->nombre_display ?? '—' }}</td>
                                    <td class="cen-sync-change-detail-cell">
                                        @php
                                            $diffChanges = is_array($change->context['changes'] ?? null)
                                                ? $change->context['changes']
                                                : [];
                                        @endphp
                                        @if ($diffChanges !== [])
                                            <ul class="cen-sync-change-diff list-unstyled"
                                                style="margin:0;font-size:12px;">
                                                @foreach ($diffChanges as $field => $values)
                                                    <li style="margin-bottom:4px;">
                                                        <strong>{{ $changeFieldLabels[$field] ?? $field }}</strong>:
                                                        <span class="text-muted">{{ $values['old'] ?? '—' }}</span>
                                                        <span aria-hidden="true">→</span>
                                                        <span>{{ $values['new'] ?? '—' }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @elseif (is_string($change->detail) && $change->detail !== '')
                                            <span style="font-size:12px;">
                                                {{ CenEstablishmentsUiCopy::changeDetailLabel($change->detail) }}
                                            </span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $change->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('voyager::establecimientos.partials.browse-voyager-pagination', [
                    'paginator' => $syncRunChanges,
                    'pageName' => 'run_changes_page',
                    'queryExcept' => [
                        'tab' => 'historial',
                        'sync_run' => $run->id,
                        'run_panel' => 'changes',
                        'change_action' => $activeChangeAction?->value,
                    ],
                ])
            @endif
        @endif
    </div>
</div>
