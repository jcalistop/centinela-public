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
                    Use «Rellenar desde evaluación preliminar» para cargar valores en el formulario. Solo puede
                    modificar
                    numerador y denominador cuando la apelación fue <strong>acogida</strong>.
                </p>
            </div>
        </div>
    @endif

    @if ($canWriteRole && !$canWrite)
        <div class="alert alert-info">
            La evaluación final solo puede editarse cuando la etapa vigente del instrumento es
            <strong>FINAL</strong>.
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
                        <th style="width:90px" class="text-center">Apelación</th>
                        <th style="width:140px" class="text-center">Acogida <span class="text-danger">*</span></th>
                        <th style="width:110px" class="text-center">Numerador</th>
                        <th style="width:110px" class="text-center">Denominador</th>
                        <th style="width:90px" class="text-center">Resultado</th>
                        <th style="width:100px" class="text-center">Cumplimiento</th>
                        <th style="width:80px" class="text-center">Ponderación</th>
                        <th style="width:110px" class="text-center">Cumpl. ponderado</th>
                        <th class="text-center">Notas de resolución</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($displayRows as $indicatorId => $row)
                        <tr wire:key="final-{{ $indicatorId }}">
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
                                @if ($row['has_appeal'])
                                    <span class="label label-warning">Sí</span>
                                @else
                                    <span class="label label-default">No</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($row['has_appeal'])
                                    <label class="radio-inline">
                                        <input type="radio" value="1"
                                            wire:model.live="evaluations.{{ $indicatorId }}.apelacion_acogida"
                                            @disabled(!$canWrite)> Sí
                                    </label>
                                    <label class="radio-inline">
                                        <input type="radio" value="0"
                                            wire:model.live="evaluations.{{ $indicatorId }}.apelacion_acogida"
                                            @disabled(!$canWrite)> No
                                    </label>
                                    @error("evaluations.{$indicatorId}.apelacion_acogida")
                                        <div class="text-danger" style="font-size:11px;margin-top:4px">
                                            {{ $message }}</div>
                                    @enderror
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($canWrite && $row['can_edit_capture'])
                                    <input type="number" step="0.01" class="form-control input-sm text-center"
                                        wire:model.live.debounce.500ms="evaluations.{{ $indicatorId }}.numerador">
                                @else
                                    <p class="form-control-static" style="margin:0">
                                        {{ $row['numerador'] !== '' ? number_format((float) $row['numerador'], 2) : '—' }}
                                    </p>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($canWrite && $row['can_edit_capture'])
                                    <input type="number" step="0.01" class="form-control input-sm text-center"
                                        wire:model.live.debounce.500ms="evaluations.{{ $indicatorId }}.denominador">
                                @else
                                    <p class="form-control-static" style="margin:0">
                                        {{ $row['denominador'] !== '' ? number_format((float) $row['denominador'], 2) : '—' }}
                                    </p>
                                @endif
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
                            <td class="text-center">
                                <button type="button"
                                    class="btn btn-xs {{ $row['resolucion_notas'] !== '' ? 'btn-info' : 'btn-default' }}"
                                    wire:click="openResolutionNotesModal({{ $indicatorId }})">
                                    <i class="voyager-{{ $row['resolucion_notas'] !== '' ? 'eye' : 'edit' }}"></i>
                                    {{ $row['resolucion_notas'] !== '' ? 'Ver notas' : 'Notas' }}
                                </button>
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
                            <td colspan="10" class="text-right">
                                <strong>Cumplimiento global</strong>
                                <br>
                                <small class="text-muted">{{ $semaforo->globalLegendFootnote() }}</small>
                            </td>
                            <td class="text-center">
                                <span class="label label-{{ $footerBadgeClass }}">
                                    <strong>{{ number_format($cumplimientoGlobal, 2) }}%</strong>
                                </span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if ($canWrite)
            <div class="clearfix" style="margin-top:10px">
                <div class="pull-left">
                    <button type="button" class="btn btn-default" wire:click="fillFromPreliminary"
                        wire:loading.attr="disabled">
                        <i class="voyager-refresh"></i> Rellenar desde evaluación preliminar
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

    @if ($showResolutionNotesModal && $resolutionNotesIndicatorId)
        @php
            $resolutionIndicator = $evaluations[$resolutionNotesIndicatorId] ?? null;
        @endphp
        <div class="modal fade in" style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1"
            role="dialog" aria-modal="true" aria-labelledby="resolution-notes-title">
            <div class="modal-dialog modal-lg" role="document" style="margin: 80px auto;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" wire:click="closeResolutionNotesModal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 id="resolution-notes-title" class="modal-title">
                            <i class="voyager-edit"></i> Notas de resolución
                            @if ($resolutionIndicator)
                                <small class="text-muted">
                                    — {{ $resolutionIndicator['clave'] }}: {{ $resolutionIndicator['nombre'] }}
                                </small>
                            @endif
                        </h4>
                    </div>
                    <div class="modal-body">
                        @if ($canWrite)
                            <p class="text-muted">
                                Los cambios quedan en el formulario y se persisten con «Guardar y recalcular».
                            </p>
                            <textarea class="form-control" rows="8" wire:model="resolutionNotesDraft" aria-label="Notas de resolución"></textarea>
                        @else
                            <p class="form-control-static" style="margin:0; white-space:pre-wrap">
                                {{ $resolutionNotesDraft !== '' ? $resolutionNotesDraft : 'Sin notas de resolución.' }}
                            </p>
                        @endif
                    </div>
                    <div class="modal-footer">
                        @if ($canWrite)
                            <button type="button" class="btn btn-primary" wire:click="applyResolutionNotes">
                                <i class="voyager-check"></i> Aplicar al formulario
                            </button>
                        @endif
                        <button type="button" class="btn btn-default" wire:click="closeResolutionNotesModal">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
