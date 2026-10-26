@php
    $rows = $referentIndicators ?? collect();
    $showSection = $rows->isNotEmpty() || ($dataTypeContent->role?->name ?? null) === 'referente';
@endphp

@if ($showSection)
    <div class="panel panel-bordered user-referent-indicators-panel">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="voyager-list"></i>
                Indicadores como referente
                @if (!empty($referentIndicatorsYear))
                    <small class="text-muted">(año {{ $referentIndicatorsYear }})</small>
                @endif
            </h3>
        </div>
        <div class="panel-body" style="padding-top: 0;">
            @if ($rows->isEmpty())
                <p class="text-muted" style="margin: 0;">
                    No hay indicadores asignados para el año activo en su Servicio de Salud.
                </p>
            @else
                <table class="table table-bordered table-hover table-striped user-referent-indicators-table"
                    style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th style="width: 90px;">Módulo</th>
                            <th style="width: 180px;">Instrumento/Compromiso</th>
                            <th style="width: 110px; white-space: nowrap;">Clave</th>
                            <th>Indicador</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    <span class="label label-default" style="font-weight: normal;">
                                        {{ $row['module'] }}
                                    </span>
                                </td>
                                <td>{{ $row['instrument'] }}</td>
                                <td style="white-space: nowrap;"><code>{{ $row['clave'] }}</code></td>
                                <td>
                                    @if (!empty($row['url']))
                                        <a href="{{ $row['url'] }}" class="user-referent-indicator-link">
                                            {{ $row['nombre'] }}
                                        </a>
                                    @else
                                        {{ $row['nombre'] }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endif
