<div>
    <div class="panel panel-bordered">
        <div class="panel-heading">
            <p class="panel-title">
                <i class="voyager-graph"></i> Tablas de sensibilidad por indicador
            </p>
        </div>
        <div class="panel-body">
            {{-- Selectors --}}
            <div class="row m-b-15">
                <div class="col-md-4">
                    <label>Convenio</label>
                    <select class="form-control" wire:model.live="selectedAgreementId">
                        <option value="">Seleccionar convenio...</option>
                        @foreach ($agreements as $agr)
                            <option value="{{ $agr->id }}">
                                {{ $agr->assignment?->user?->name ?? '?' }}
                                —
                                {{ $agr->assignment?->position?->nombre ?? '?' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label>Periodo</label>
                    <select class="form-control" wire:model.live="selectedPeriodId">
                        <option value="">Seleccionar periodo...</option>
                        @foreach ($periods as $prd)
                            <option value="{{ $prd->id }}">
                                Periodo {{ $prd->sequence }}
                                ({{ $prd->start_date?->format('Y-m-d') ?? '?' }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label>Indicador</label>
                    <select class="form-control" wire:model.live="selectedIndicatorId">
                        <option value="">Seleccionar indicador...</option>
                        @foreach ($indicators as $ind)
                            <option value="{{ $ind->id }}">
                                {{ $ind->clave }} — {{ $ind->nombre }}
                                ({{ $ind->objective?->clave ?? '?' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($selectedIndicatorId !== null)
                <hr>

                @if ($table !== null && $editingTableId === null)
                    {{-- Tabla existente (vista) --}}
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <strong>{{ $table->nombre }}</strong>
                            @if ($canEdit)
                                <div class="panel-actions">
                                    <button class="btn btn-xs btn-default" wire:click="editTable({{ $table->id }})">
                                        <i class="voyager-edit"></i> Editar
                                    </button>
                                    <button class="btn btn-xs btn-danger" wire:click="deleteTable({{ $table->id }})"
                                        wire:confirm="¿Eliminar tabla de sensibilidad?">
                                        <i class="voyager-trash"></i>
                                    </button>
                                </div>
                            @endif
                        </div>
                        <div class="panel-body">
                            <table class="table table-bordered table-condensed">
                                <thead>
                                    <tr>
                                        <th>Orden</th>
                                        <th>Valor mínimo</th>
                                        <th>Op. mínimo</th>
                                        <th>Valor máximo</th>
                                        <th>Op. máximo</th>
                                        <th>Cumplimiento %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($table->rows->sortBy('orden') as $row)
                                        <tr>
                                            <td>{{ $row->orden }}</td>
                                            <td>{{ $row->valor_minimo }}</td>
                                            <td>{{ $row->operador_minimo }}</td>
                                            <td>{{ $row->valor_maximo }}</td>
                                            <td>{{ $row->operador_maximo }}</td>
                                            <td>{{ $row->cumplimiento }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if ($canEdit && $editingTableId !== null)
                    {{-- Formulario de edición --}}
                    <div class="well m-b-15">
                        <h5>{{ $editingTableId ? 'Editar tabla de sensibilidad' : 'Nueva tabla de sensibilidad' }}</h5>
                        <form wire:submit.prevent="saveTable">
                            <div class="form-group">
                                <label>Nombre de la tabla</label>
                                <input type="text" class="form-control" wire:model="tableName">
                                @error('tableName') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            @error('rows') <div class="alert alert-danger">{{ $message }}</div> @enderror

                            <table class="table table-bordered table-condensed">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Valor mínimo</th>
                                        <th>Op. mínimo</th>
                                        <th>Valor máximo</th>
                                        <th>Op. máximo</th>
                                        <th>Cumplimiento %</th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $i => $row)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>
                                                <input type="text" class="form-control input-sm"
                                                    wire:model="rows.{{ $i }}.valor_minimo">
                                            </td>
                                            <td>
                                                <select class="form-control input-sm"
                                                    wire:model="rows.{{ $i }}.operador_minimo">
                                                    <option value=">=">>=</option>
                                                    <option value=">">></option>
                                                    <option value="=">=</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control input-sm"
                                                    wire:model="rows.{{ $i }}.valor_maximo">
                                            </td>
                                            <td>
                                                <select class="form-control input-sm"
                                                    wire:model="rows.{{ $i }}.operador_maximo">
                                                    <option value="<="><=</option>
                                                    <option value="<"><</option>
                                                    <option value="=">=</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control input-sm"
                                                    wire:model="rows.{{ $i }}.cumplimiento">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-xs btn-danger"
                                                    wire:click="removeRow({{ $i }})"
                                                    {{ count($rows) <= 1 ? 'disabled' : '' }}>
                                                    <i class="voyager-x"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <div class="m-b-10">
                                <button type="button" class="btn btn-xs btn-default" wire:click="addRow">
                                    <i class="voyager-plus"></i> Agregar fila
                                </button>
                            </div>

                            <div>
                                <button type="submit" class="btn btn-success btn-sm">
                                    {{ $editingTableId ? 'Guardar cambios' : 'Crear tabla' }}
                                </button>
                                <button type="button" class="btn btn-default btn-sm" wire:click="cancelTableForm">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                @elseif ($canEdit && $table === null)
                    <p class="text-muted">
                        Este indicador no tiene tabla de sensibilidad.
                        <button class="btn btn-xs btn-primary" wire:click="createTable">
                            <i class="voyager-plus"></i> Crear tabla
                        </button>
                    </p>
                @endif
            @endif
        </div>
    </div>
</div>
