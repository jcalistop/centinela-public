<div>
    @if (session()->has('stage_message'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            {{ session('stage_message') }}
        </div>
    @endif

    @if ($canWriteRole)
        <div class="row" style="margin-bottom:10px">
            <div class="col-md-12">
                <p class="text-muted" style="margin:0">
                    Capture numerador y denominador de la evaluación preliminar por indicador en convenio.
                </p>
            </div>
        </div>
    @endif

    @if ($canWriteRole && !$canWrite)
        <div class="alert alert-info">
            La evaluación preliminar solo puede editarse cuando la etapa vigente del instrumento es
            <strong>PRELIMINAR</strong>.
        </div>
    @endif

    <div class="no-print">
        <x-metas.semaforo-legend context="mixed" />
    </div>

    @if (empty($displayRows))
        <div class="alert alert-warning">No hay indicadores en convenio y aplicables para este establecimiento.</div>
    @else
        <div class="table-responsive">
            <table
                class="table table-bordered table-striped table-condensed table-results metas-instrument-table metas-results-table">
                <thead>
                    <tr>
                        <th class="metas-results-col--codigo metas-results-col--nowrap">Código</th>
                        <th class="metas-results-col--nombre text-left">Indicador</th>
                        <th style="width:90px" class="text-center">Meta efectiva</th>
                        <th style="width:110px" class="text-center">Numerador</th>
                        <th style="width:110px" class="text-center">Denominador</th>
                        <th style="width:90px" class="text-center">Resultado</th>
                        <th style="width:100px" class="text-center">Cumplimiento</th>
                        <th style="width:80px" class="text-center">Ponderación</th>
                        <th style="width:110px" class="text-center">Cumpl. ponderado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($displayRows as $indicatorId => $row)
                        <tr wire:key="prelim-{{ $indicatorId }}">
                            <td class="metas-results-col--nowrap"><code
                                    class="metas-results-code">{{ $row['clave'] }}</code></td>
                            <td class="metas-results-col--nombre text-left">{{ $row['nombre'] }}</td>
                            <td class="text-center">
                                @include('livewire.metas-instrument.partials.indicator-meta-value', [
                                    'value' => $row['meta_efectiva'] !== '' ? $row['meta_efectiva'] : null,
                                    'metaOperador' => $row['meta_operador'] ?? null,
                                    'unidadMedida' => $row['unidad_medida'] ?? null,
                                ])
                            </td>
                            <td class="text-center">
                                <input type="number" step="0.01" class="form-control input-sm text-center"
                                    wire:model.live.debounce.500ms="evaluations.{{ $indicatorId }}.numerador"
                                    @disabled(!$canWrite)>
                            </td>
                            <td class="text-center">
                                <input type="number" step="0.01" class="form-control input-sm text-center"
                                    wire:model.live.debounce.500ms="evaluations.{{ $indicatorId }}.denominador"
                                    @disabled(!$canWrite)>
                            </td>
                            <td class="text-center">
                                @if ($row['resultado'] !== null)
                                    @include('livewire.metas-instrument.partials.indicator-result-value', [
                                        'value' => $row['resultado'],
                                        'decimales' => $row['decimales'],
                                        'unidadMedida' => $row['unidad_medida'],
                                    ])
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($row['cumplimiento'] !== null)
                                    <span class="label label-{{ $row['cumplimiento_label_class'] }}">
                                        {{ number_format($row['cumplimiento'], 2) }}%
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">{{ number_format((float) $row['ponderacion'], 2) }}%</td>
                            <td class="text-center">
                                @if ($row['cumpl_ponderado'] !== null)
                                    {{ number_format($row['cumpl_ponderado'], 2) }}%
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                @if ($hasCumplimientoGlobal)
                    @php
                        $semaforo = \App\Support\Metas\MetSemaforoThresholds::forIndicatorType(
                            (int) $indicatorTypeId,
                            $indicatorTypeRoute,
                        );
                        $footerBadgeClass = $semaforo->labelClass($cumplimientoGlobal);
                    @endphp
                    <tfoot>
                        <tr style="background:#eaf2fb">
                            <td colspan="8" class="text-right">
                                <strong>Cumplimiento global</strong>
                                <br>
                                <small class="text-muted">{{ $semaforo->globalLegendFootnote() }}</small>
                            </td>
                            <td class="text-center">
                                <span class="label label-{{ $footerBadgeClass }}">
                                    <strong>{{ number_format($cumplimientoGlobal, 2) }}%</strong>
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($canWrite)
            <div class="clearfix" style="margin-top:10px">
                <div class="pull-left">
                    <button type="button" class="btn btn-default" wire:click="fillFromAutoevaluacion"
                        wire:loading.attr="disabled">
                        <i class="voyager-refresh"></i> Rellenar desde autoevaluación
                    </button>
                    <button type="button" class="btn btn-warning" wire:click="clearAllData"
                        wire:loading.attr="disabled">
                        <i class="voyager-trash"></i> Limpiar todos los datos
                    </button>
                </div>
                <div class="pull-right">
                    <button type="button" class="btn btn-primary" wire:click="recalculate"
                        wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="recalculate">
                            <i class="voyager-check"></i> Guardar y recalcular
                        </span>
                        <span wire:loading wire:target="recalculate">
                            <i class="voyager-refresh"></i> Guardando…
                        </span>
                    </button>
                </div>
            </div>
        @endif
    @endif
</div>
