@if (is_array($dashboard))
    @php
        $previewQuery =
            !empty($dashboard['preview']) && !empty($previewSsId) ? '?preview=1&ss=' . (int) $previewSsId : '';
        $alertasDetailUrl = route('centinela.dashboard.detail', ['section' => 'alertas']) . $previewQuery;
    @endphp
    <div class="admin-dashboard-compass cen-compass-ui admin-dashboard-compass--ambito-legend" style="margin-top: 1rem;">
        <div class="cen-dashboard-shell">
            @if (!empty($dashboard['preview']))
                <div class="alert alert-info" style="margin-bottom: 1rem;">
                    <strong>Contexto de preview:</strong>
                    {{ $dashboard['context']['ss_nombre'] ?? '—' }}
                    · año {{ $dashboard['context']['year_ano'] ?? '—' }}
                </div>
            @else
                @include('vendor.voyager.partials.dashboard-welcome', ['compact' => true])
            @endif

            <div class="row">
                <div class="col-md-6" style="margin-bottom: 1rem;">
                    <x-centinela.compass.dashboard-block title="Indicadores por ámbito" icon="voyager-tag"
                        description="Promedio de cumplimientos">
                        @php $ambitos = $dashboard['ambitos'] ?? []; @endphp
                        @if (count($ambitos) === 0)
                            <p class="text-muted" style="margin: 0;">Sin datos de ámbitos en este contexto.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover cen-compass-table cen-dashboard-metric-table"
                                    style="margin-bottom: 0;">
                                    <tbody>
                                        @foreach ($ambitos as $row)
                                            <tr>
                                                <td>
                                                    {{ $row['label'] }}
                                                    @include(
                                                        'vendor.voyager.partials.dashboard-indicator-count-tag',
                                                        [
                                                            'count' => $row['indicator_count'] ?? 0,
                                                        ]
                                                    )
                                                </td>
                                                <td class="cen-dashboard-metric-table__value">
                                                    <x-centinela.dashboard.ambito-cumplimiento-value
                                                        :percent="$row['percent'] ?? null" />
                                                </td>
                                                <td class="cen-dashboard-metric-table__action">
                                                    <a href="{{ $row['detail_url'] }}" class="btn btn-sm btn-default"
                                                        title="Ver detalle" aria-label="Ver detalle">
                                                        <i class="voyager-angle-right"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-centinela.compass.dashboard-block>

                    <x-centinela.compass.dashboard-block title="Indicadores por instrumento" icon="voyager-window-list"
                        style="margin-top: 1rem;">
                        @php $instrumentos = array_filter($dashboard['instrumentos'] ?? [], fn ($i) => ($i['visible'] ?? true)); @endphp
                        <div class="table-responsive">
                            <table class="table table-hover cen-compass-table cen-dashboard-metric-table"
                                style="margin-bottom: 0;">
                                <tbody>
                                    @forelse ($instrumentos as $row)
                                        <tr>
                                            <td>
                                                {{ $row['label'] }}
                                                @include(
                                                    'vendor.voyager.partials.dashboard-indicator-count-tag',
                                                    [
                                                        'count' => $row['indicator_count'] ?? 0,
                                                    ]
                                                )
                                            </td>
                                            <td class="cen-dashboard-metric-table__value">
                                                <x-centinela.dashboard.ambito-cumplimiento-value :percent="$row['percent'] ?? null" />
                                            </td>
                                            <td class="cen-dashboard-metric-table__action">
                                                <a href="{{ $row['detail_url'] }}" class="btn btn-sm btn-default"
                                                    title="Abrir módulo" aria-label="Abrir módulo">
                                                    <i class="voyager-angle-right"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-muted">Sin instrumentos visibles.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-centinela.compass.dashboard-block>

                    <x-centinela.compass.dashboard-block title="Establecimientos" icon="voyager-company"
                        description="Promedio de cumplimiento de todos los indicadores aplicables en los diferentes instrumentos"
                        style="margin-top: 1rem;">
                        @php $top = $dashboard['top_establecimientos'] ?? []; @endphp
                        @if (count($top) === 0)
                            <p class="text-muted" style="margin: 0;">Sin resultados por establecimiento.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover cen-compass-table cen-dashboard-metric-table"
                                    style="margin-bottom: 0;">
                                    <tbody>
                                        @foreach ($top as $row)
                                            <tr>
                                                <td>
                                                    {{ $row['rank'] }}. {{ $row['center_label'] }}
                                                    @include(
                                                        'vendor.voyager.partials.dashboard-indicator-count-tag',
                                                        [
                                                            'count' => $row['indicator_count'] ?? 0,
                                                        ]
                                                    )
                                                </td>
                                                <td class="cen-dashboard-metric-table__value">
                                                    <x-centinela.dashboard.ambito-cumplimiento-value
                                                        :percent="$row['percent'] ?? null" />
                                                </td>
                                                <td class="cen-dashboard-metric-table__action">
                                                    <a href="{{ $row['detail_url'] ?? '#' }}"
                                                        class="btn btn-sm btn-default" title="Ver indicadores"
                                                        aria-label="Ver indicadores">
                                                        <i class="voyager-angle-right"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-centinela.compass.dashboard-block>
                </div>

                <div class="col-md-6" style="margin-bottom: 1rem;">
                    <x-centinela.compass.dashboard-block title="Alertas" icon="voyager-bell">
                        @php $alertas = $dashboard['alertas'] ?? []; @endphp
                        @if (count($alertas) === 0)
                            <p class="text-muted" style="margin: 0;">No hay alertas pendientes en este contexto.</p>
                        @else
                            <ul class="list-unstyled" style="margin-bottom: 0;">
                                @foreach (array_slice($alertas, 0, 5) as $alert)
                                    <li style="margin-bottom: 0.75rem;">
                                        <strong>{{ $alert['title'] }}</strong>
                                        <div class="text-muted small">{{ $alert['body'] }}</div>
                                        @if (!empty($alert['url']))
                                            <a href="{{ $alert['url'] }}" class="btn btn-sm btn-warning"
                                                style="margin-top: 0.35rem;">Abrir</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @if (count($alertas) > 5)
                                <a href="{{ $alertasDetailUrl }}" class="btn btn-sm btn-warning"
                                    style="margin-top: 0.5rem;">Ver todas</a>
                            @endif
                        @endif
                    </x-centinela.compass.dashboard-block>

                    <x-centinela.compass.dashboard-block title="Destacados" icon="voyager-star"
                        style="margin-top: 1rem;">
                        @php $destacados = $dashboard['destacados'] ?? []; @endphp
                        @if (count($destacados) === 0)
                            <p class="text-muted" style="margin: 0;">No hay notas destacadas en la bitácora.</p>
                        @else
                            <ul class="list-unstyled" style="margin-bottom: 0;">
                                @foreach ($destacados as $item)
                                    <li
                                        style="margin-bottom: 0.75rem; border-left: 3px solid #c57c00; background: #fff8e7; border-radius: 4px; padding: 10px 12px;">
                                        <div class="text-muted small" style="margin-bottom: 0.25rem;">
                                            <i class="voyager-star"></i> {{ $item['module'] }}
                                            · {{ $item['author'] }} · {{ $item['registered_at'] }}
                                        </div>
                                        <strong>{{ $item['title'] }}</strong>
                                        <div class="text-muted small" style="margin-top: 0.25rem;">{{ $item['body'] }}
                                        </div>
                                        @if (!empty($item['url']))
                                            <a href="{{ $item['url'] }}" class="btn btn-sm btn-warning"
                                                style="margin-top: 0.35rem;">Ver bitácora</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-centinela.compass.dashboard-block>
                </div>
            </div>

            <x-centinela.dashboard.ambito-semaforo-legend />
        </div>
    </div>
@endif
