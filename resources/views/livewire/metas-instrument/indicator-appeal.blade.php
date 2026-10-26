<div>
    @if (session()->has('stage_message'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            {{ session('stage_message') }}
        </div>
    @endif

    <div class="row" style="margin-bottom:10px">
        <div class="col-md-12">
            <p class="text-muted" style="margin:0">
                @if ($isAppealPeriodClosed)
                    Consulta las apelaciones registradas y descarga los documentos adjuntos.
                @else
                    Registre apelaciones por indicador durante la etapa de apelación.
                @endif
            </p>
        </div>
    </div>

    @if (!$canEdit && !$isAppealPeriodClosed)
        <div class="alert alert-info">
            Solo un responsable designado del establecimiento (o administración) puede registrar o editar apelaciones en
            etapa
            <strong>APELACIÓN</strong>. Puede consultar apelaciones existentes con el botón <strong>Ver</strong>.
        </div>
    @endif

    @if ($showForm && $editingIndicatorId)
        <div class="modal fade in" style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1"
            role="dialog" aria-modal="true" aria-labelledby="appeal-modal-title">
            <div class="modal-dialog modal-lg" role="document" style="margin: 80px auto;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" wire:click="closeAppealForm">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 id="appeal-modal-title" class="modal-title">
                            <i class="voyager-{{ $viewOnly ? 'eye' : 'edit' }}"></i>
                            @if ($viewOnly)
                                Detalle de apelación —
                            @else
                                Apelación —
                            @endif
                            {{ $indicators[$editingIndicatorId]['nombre'] ?? '' }}
                        </h4>
                    </div>
                    <div class="modal-body" style="max-height: calc(100vh - 200px); overflow-y:auto;">
                        <div class="form-group">
                            <label>Tipo de apelación</label>
                            @if ($viewOnly)
                                <p class="form-control-static" style="margin:0">
                                    {{ $appealTypeOptions[$appealType] ?? $appealType }}
                                </p>
                            @else
                                <select class="form-control" wire:model="appealType">
                                    @foreach ($appealTypeOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="form-group">
                            <label>Motivo</label>
                            @if ($viewOnly)
                                <p class="form-control-static" style="margin:0; white-space:pre-wrap">
                                    {{ $motivo }}</p>
                            @else
                                <textarea class="form-control" rows="6" wire:model="motivo"></textarea>
                                @error('motivo')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            @endif
                        </div>

                        @if ($appealSignatureLabel !== '')
                            <div class="alert alert-info" style="margin-bottom: 1rem;">
                                <i class="voyager-person"></i> {{ $appealSignatureLabel }}
                            </div>
                        @endif

                        <div class="form-group">
                            <label>Documentos adjuntos</label>
                            @if (count($existingAttachments) === 0 && $viewOnly)
                                <p class="text-muted" style="margin:0">Sin documentos adjuntos.</p>
                            @endif
                            @if (count($existingAttachments) > 0)
                                <ul class="list-group" style="margin-bottom:10px">
                                    @foreach ($existingAttachments as $index => $archivo)
                                        <li class="list-group-item"
                                            wire:key="appeal-file-{{ $editingIndicatorId }}-{{ $index }}"
                                            style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                                            <span style="min-width:0;overflow:hidden;text-overflow:ellipsis">
                                                <i class="voyager-file-text"></i>
                                                {{ $archivo['name'] ?? 'Adjunto' }}
                                            </span>
                                            <span style="flex-shrink:0">
                                                @if ($editingAppealId)
                                                    <a href="{{ route('metas.instrument.appeal.attachment.download', [
                                                        'indicatorType' => $indicatorType,
                                                        'appeal' => $editingAppealId,
                                                        'attachmentIndex' => $index,
                                                    ]) }}"
                                                        class="btn btn-xs btn-info centinela-download-guard-link"
                                                        data-centinela-download-fallback="{{ $archivo['name'] ?? 'adjunto' }}">
                                                        <i class="voyager-download"></i> Descargar
                                                    </a>
                                                @endif
                                                @if ($canEdit && !$viewOnly)
                                                    <button type="button" class="btn btn-xs btn-danger"
                                                        wire:click="removeExistingAttachment({{ $index }})"
                                                        wire:confirm="¿Eliminar este documento adjunto?">
                                                        <i class="voyager-trash"></i> Eliminar
                                                    </button>
                                                @endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($canEdit && !$viewOnly)
                                <label class="text-muted" style="font-weight:normal">Agregar documentos</label>
                                <input type="file" class="form-control" wire:model="newAttachments" multiple
                                    data-max-upload-bytes="{{ \App\Support\Upload\UploadSizeLimit::maxBytes() }}"
                                    data-max-upload-label="{{ \App\Support\Upload\UploadSizeLimit::humanLabel() }}"
                                    style="padding-bottom:2.5rem">
                                <x-upload-size-hint />
                                @error('newAttachments.*')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer">
                        @if ($canEdit && !$viewOnly)
                            <button type="button" class="btn btn-primary" wire:click="saveAppeal">
                                <i class="voyager-check"></i> Guardar apelación
                            </button>
                        @endif
                        <button type="button" class="btn btn-default" wire:click="closeAppealForm">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="no-print">
        <x-metas.semaforo-legend context="mixed" />
    </div>

    <div class="table-responsive">
        <table
            class="table table-bordered table-striped table-condensed table-results metas-instrument-table metas-results-table">
            <thead>
                <tr>
                    <th class="metas-results-col--codigo metas-results-col--nowrap">Código</th>
                    <th class="metas-results-col--nombre text-left">Indicador</th>
                    <th style="width:90px" class="text-center">Meta efectiva</th>
                    <th style="width:90px" class="text-center">Resultado</th>
                    <th style="width:100px" class="text-center">Cumplimiento</th>
                    <th style="width:80px" class="text-center">Ponderación</th>
                    <th style="width:110px" class="text-center">Cumpl. ponderado</th>
                    <th style="width:120px" class="text-center">Estado</th>
                    <th style="width:140px" class="text-center">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($displayRows as $indicatorId => $row)
                    <tr wire:key="appeal-{{ $indicatorId }}">
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
                            @if ($row['has_appeal'])
                                <span class="label label-warning">Apelado</span>
                                @if (count($row['archivos'] ?? []) > 0)
                                    <br><small class="text-muted">{{ count($row['archivos']) }} adjunto(s)</small>
                                @endif
                            @else
                                <span class="label label-default">Sin apelación</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($row['has_appeal'] && $canView)
                                <button type="button" class="btn btn-xs btn-info"
                                    wire:click="openAppealView({{ $indicatorId }})">
                                    <i class="voyager-eye"></i> Ver
                                </button>
                            @endif
                            @if ($canEdit)
                                <button type="button" class="btn btn-xs btn-warning"
                                    wire:click="openAppealForm({{ $indicatorId }})">
                                    {{ $row['has_appeal'] ? 'Editar' : 'Apelar' }}
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted">Sin indicadores en convenio.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
