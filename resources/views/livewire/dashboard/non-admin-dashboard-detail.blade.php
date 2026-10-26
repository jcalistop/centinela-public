<div class="page-content compass container-fluid">
    @include('voyager::alerts')

    <div
        class="admin-dashboard-compass cen-compass-ui @if (in_array($section, ['ambitos', 'establecimientos'], true)) admin-dashboard-compass--ambito-legend @endif">
        <div class="cen-dashboard-shell">
            <x-centinela.dashboard.back-navigation :preview-mode="$previewMode" :preview-ss-id="$previewSsId" />

            <x-centinela.compass.dashboard-block :title="$title" icon="voyager-list">
                @if (count($rows) === 0)
                    <p class="text-muted" style="margin: 0;">Sin registros para mostrar.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped cen-compass-table">
                            <thead>
                                <tr>
                                    <th>Indicador / ítem</th>
                                    @if ($section !== 'alertas')
                                        <th>
                                            @if ($section === 'ambitos')
                                                Observaciones
                                            @elseif ($section === 'establecimientos')
                                                Instrumento
                                            @else
                                                Establecimiento / detalle
                                            @endif
                                        </th>
                                        <th class="text-right">Cumplimiento</th>
                                    @else
                                        <th>Detalle</th>
                                    @endif
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr @class(['active' => !empty($row['is_summary'])])>
                                        <td>
                                            @if (!empty($row['is_summary']))
                                                <strong>{{ $row['label'] }}</strong>
                                            @else
                                                {{ $row['label'] }}
                                                @if (!empty($row['source']))
                                                    <span class="text-muted small">({{ $row['source'] }})</span>
                                                @endif
                                            @endif
                                        </td>
                                        @if ($section !== 'alertas')
                                            <td>{{ $row['establishment'] ?? '—' }}</td>
                                            <td class="text-right">
                                                @if (in_array($section, ['ambitos', 'establecimientos'], true))
                                                    <x-centinela.dashboard.ambito-cumplimiento-value :percent="$row['percent'] ?? null"
                                                        :bold="!empty($row['is_summary'])" />
                                                @else
                                                    @if (!empty($row['is_summary']))
                                                        <strong>{{ $row['percent'] !== null ? $row['percent'] . '%' : '—' }}</strong>
                                                    @else
                                                        {{ $row['percent'] !== null ? $row['percent'] . '%' : '—' }}
                                                    @endif
                                                @endif
                                            </td>
                                        @else
                                            <td>{{ $row['establishment'] ?? '—' }}</td>
                                        @endif
                                        <td class="text-right" style="white-space: nowrap;">
                                            @if (!empty($row['url']) && empty($row['is_summary']))
                                                <a href="{{ $row['url'] }}" @class([
                                                    'btn btn-sm',
                                                    'btn-warning' => $section === 'alertas',
                                                    'btn-primary' => $section !== 'alertas',
                                                ])>Abrir</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-centinela.compass.dashboard-block>

            @if (in_array($section, ['ambitos', 'establecimientos'], true))
                <x-centinela.dashboard.ambito-semaforo-legend />
            @endif
        </div>
    </div>
</div>
