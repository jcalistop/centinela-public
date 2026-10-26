<div>
    @if (empty($establishmentsData))
        <p class="text-muted text-center">
            No hay establecimientos configurados para este indicador.
        </p>
    @else
        <table class="table table-bordered table-condensed metas-instrument-table metas-establishments-table">
            <thead>
                <tr>
                    <th style="width:90px">Código</th>
                    <th>Establecimiento</th>
                    <th style="width:80px" class="text-center">Aplica</th>
                    <th style="width:100px" class="text-center">En convenio</th>
                    <th style="width:90px" class="text-center">Agrupa SS</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($establishmentsData as $row)
                    <tr wire:key="indicator-establishment-{{ $row['cen_center_id'] }}">
                        <td><code class="metas-establishments-code">{{ $row['codigo'] }}</code></td>
                        <td>
                            <strong>{{ $row['nombre'] }}</strong>
                            @if (!$usesInternalUnitMeasurement)
                                @foreach ($row['adosados'] ?? [] as $adosadoNombre)
                                    <br><span
                                        class="label label-info metas-center-adosado-label">{{ $adosadoNombre }}</span>
                                @endforeach
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($row['aplica'])
                                <span class="label label-success">Sí</span>
                            @else
                                <span class="label label-default">No</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($row['convenido'])
                                <span class="label label-primary">Sí</span>
                            @else
                                <span class="label label-default">No</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($row['agrupa_ss'])
                                <span class="label label-warning" title="Agrupa resultados a nivel SS">Sí</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <x-metas.establishments-tab-legend :show-adosado-legend="!$usesInternalUnitMeasurement" />
</div>
