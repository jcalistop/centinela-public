@if (empty($desempenoData))
    <p class="text-muted text-center">
        No hay establecimientos con este indicador marcado como aplica para su Servicio de Salud
        en el año activo.
    </p>
@else
    <table class="table table-bordered table-condensed metas-instrument-table metas-desempeno-table">
        <thead>
            <tr>
                <th>Establecimiento</th>
                <th style="width:90px" class="text-center">En convenio</th>
                <th style="width:90px" class="text-center">Meta efectiva</th>
                <th style="width:70px" class="text-center">Datos al</th>
                <th style="width:120px" class="text-center"
                    title="En fórmulas simples: numerador ÷ denominador del período. Si no hay un par único num/den: Fórmula compleja.">
                    Fórmula de cálculo
                </th>
                <th style="width:90px" class="text-center">Resultado</th>
                <th style="width:110px" class="text-center">Cumplimiento</th>
                <th style="width:120px" class="text-center no-print">Datos</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($desempenoData as $row)
                @php
                    $cumplClass = \App\Support\Metas\MetSemaforoThresholds::labelClassForIndicatorCompliance(
                        $row['cumplimiento'],
                        $desempenoUmbralSuperior,
                        $desempenoUmbralInferior ?? null,
                    );
                @endphp
                <tr>
                    <td>
                        {{ $row['centro_nombre'] }}
                        @if ($row['centro_codigo'])
                            <small class="text-muted">({{ $row['centro_codigo'] }})</small>
                        @endif
                        @if (!empty($row['agrupa_ss']))
                            <br><span class="label label-warning" title="Agrupa resultados a nivel SS">Agrupa SS</span>
                        @endif
                        @if (!empty($row['incomplete_period_count']))
                            <div class="metas-desempeno-incomplete-alert">
                                <x-metas.incomplete-period-alert variant="center" :period-count="$row['incomplete_period_count']" :period-labels="$row['incomplete_period_labels'] ?? []" />
                            </div>
                        @endif
                    </td>
                    <td class="text-center">
                        @if ($row['convenido'])
                            <span class="label label-success">Sí</span>
                        @else
                            <span class="label label-default">No</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @include('livewire.metas-instrument.partials.indicator-meta-value', [
                            'value' => $row['meta_efectiva'],
                            'metaOperador' => $row['meta_op'] ?? null,
                            'unidadMedida' => $row['unidad_medida'] ?? null,
                        ])
                    </td>
                    <x-metas.datos-al-badge :ultimo-mes-cargado="$row['ultimo_mes_cargado'] ?? null" :estado-carga="$row['estado_carga'] ?? 'sin_datos'" />
                    @if ($row['error'])
                        @include('livewire.metas-instrument.partials.result-issue-from-stored-message', [
                            'storedMessage' => $row['error'],
                            'colspan' => 3,
                        ])
                    @elseif ($row['resultado'] === null && $row['numerador'] === null && $row['denominador'] === null)
                        <td colspan="2" class="text-center">
                            <span class="text-muted">Sin calcular</span>
                        </td>
                    @elseif ($desempenoUsesComplexPrimaryComponents)
                        <td class="text-center text-muted metas-results-complex-formula">
                            <button type="button"
                                class="btn btn-link btn-xs metas-complex-formula-variables-trigger no-print no-capture"
                                wire:click="openComplexFormulaVariablesModal({{ (int) $indicatorId }}, {{ (int) $row['cen_center_id'] }}, @js($row['ultimo_periodo'] ?? null), true)"
                                title="Ver variables de cálculo">
                                Fórmula compleja
                            </button>
                            <span class="print-only">Fórmula compleja</span>
                        </td>
                        <td class="text-center">
                            @include('livewire.metas-instrument.partials.indicator-result-value', [
                                'value' => (float) $row['resultado'],
                                'decimales' => $row['decimales'],
                                'unidadMedida' => $row['unidad_medida'] ?? null,
                            ])
                        </td>
                    @else
                        <td class="text-center">
                            {{ \App\Support\Metas\MetIndicatorMetaPresenter::formatCompactNumber((float) $row['numerador']) .
                                ' / ' .
                                \App\Support\Metas\MetIndicatorMetaPresenter::formatCompactNumber((float) $row['denominador']) }}
                        </td>
                        <td class="text-center">
                            @include('livewire.metas-instrument.partials.indicator-result-value', [
                                'value' => (float) $row['resultado'],
                                'decimales' => $row['decimales'],
                                'unidadMedida' => $row['unidad_medida'] ?? null,
                            ])
                        </td>
                    @endif
                    @if (!$row['error'])
                        <td class="text-center">
                            @if ($row['cumplimiento'] !== null)
                                <span class="label label-{{ $cumplClass }}">
                                    {{ number_format($row['cumplimiento'], 2) }}%
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    @endif
                    <td class="text-center no-print">
                        <x-metas.indicator-data-actions :wire-click="'openDataModal(' . $row['cen_center_id'] . ')'" />
                    </td>
                </tr>
            @endforeach
            @if ($desempenoPromedioCumplimiento !== null)
                @php
                    $promedioCumplClass = \App\Support\Metas\MetSemaforoThresholds::labelClassForIndicatorCompliance(
                        $desempenoPromedioCumplimiento,
                        $desempenoUmbralSuperior,
                        $desempenoUmbralInferior ?? null,
                    );
                    $promedioColspan = 6;
                @endphp
                <tr class="active">
                    <td colspan="{{ $promedioColspan }}" class="text-right">
                        <strong>Promedio cumplimiento</strong>
                    </td>
                    <td class="text-center">
                        <span class="label label-{{ $promedioCumplClass }}">
                            {{ number_format($desempenoPromedioCumplimiento, 2) }}%
                        </span>
                    </td>
                    <td class="text-center no-print"></td>
                </tr>
            @endif
        </tbody>
    </table>
    <div class="no-print">
        <x-metas.datos-al-legend />
    </div>
@endif
