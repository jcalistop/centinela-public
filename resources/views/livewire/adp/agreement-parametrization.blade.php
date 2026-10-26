<div>
    <div class="panel panel-bordered">
        <div class="panel-heading">
            <p class="panel-title">
                <i class="voyager-list"></i> Parametrización de convenios, objetivos e indicadores
            </p>
        </div>
        <div class="panel-body">
            {{-- Selector de convenio --}}
            <div class="row m-b-15">
                <div class="col-md-6">
                    <label>Convenio</label>
                    <select class="form-control" wire:model.live="selectedAgreementId">
                        <option value="">Seleccionar convenio...</option>
                        @foreach ($agreements as $agr)
                            <option value="{{ $agr->id }}">
                                {{ $agr->assignment?->user?->name ?? '?' }}
                                —
                                {{ $agr->assignment?->position?->nombre ?? '?' }}
                                ({{ $agr->agreement_start?->format('Y-m-d') ?? '?' }})
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
                                ({{ $prd->start_date?->format('Y-m-d') ?? '?' }}
                                —
                                {{ $prd->end_date?->format('Y-m-d') ?? '?' }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($selectedPeriodId !== null)
                <hr>

                {{-- Objetivos --}}
                <h4>
                    Objetivos
                    @if ($canEdit)
                        <button class="btn btn-xs btn-primary" wire:click="createObjective">
                            <i class="voyager-plus"></i> Nuevo objetivo
                        </button>
                    @endif
                </h4>

                @if ($showObjectiveForm)
                    <div class="well m-b-15">
                        <form wire:submit.prevent="saveObjective">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Clave</label>
                                        <input type="text" class="form-control" wire:model="objClave">
                                        @error('objClave') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Nombre</label>
                                        <input type="text" class="form-control" wire:model="objNombre">
                                        @error('objNombre') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Orden</label>
                                        <input type="number" class="form-control" wire:model="objOrden" min="1">
                                        @error('objOrden') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Descripción</label>
                                <textarea class="form-control" rows="2" wire:model="objDescripcion"></textarea>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-success btn-sm">
                                    {{ $editingObjectiveId ? 'Guardar' : 'Crear objetivo' }}
                                </button>
                                <button type="button" class="btn btn-default btn-sm" wire:click="cancelObjectiveForm">Cancelar</button>
                            </div>
                        </form>
                    </div>
                @endif

                @forelse ($objectives as $obj)
                    <div class="panel panel-default m-b-15">
                        <div class="panel-heading">
                            <strong>{{ $obj->clave }}</strong>
                            — {{ $obj->nombre }}
                            <span class="label label-default">Orden {{ $obj->orden }}</span>
                            @if ($canEdit)
                                <div class="panel-actions">
                                    <button class="btn btn-xs btn-default" wire:click="editObjective({{ $obj->id }})">
                                        <i class="voyager-edit"></i> Editar objetivo
                                    </button>
                                    <button class="btn btn-xs btn-danger" wire:click="deleteObjective({{ $obj->id }})"
                                        wire:confirm="¿Eliminar objetivo y sus indicadores?">
                                        <i class="voyager-trash"></i>
                                    </button>
                                </div>
                            @endif
                        </div>
                        <div class="panel-body">
                            @if ($obj->descripcion)
                                <p class="text-muted">{{ $obj->descripcion }}</p>
                            @endif

                            {{-- Indicadores del objetivo --}}
                            <h5>
                                Indicadores
                                @if ($canEdit)
                                    <button class="btn btn-xs btn-primary"
                                        wire:click="createIndicator({{ $obj->id }})">
                                        <i class="voyager-plus"></i> Nuevo indicador
                                    </button>
                                @endif
                            </h5>

                            {{-- Indicator form inline --}}
                            @if ($canEdit && $catalogObjectiveId === $obj->id)
                                <div class="well m-b-15">
                                    <h5>{{ $editingIndicatorId ? 'Editar indicador' : 'Nuevo indicador' }}</h5>
                                    <form wire:submit.prevent="saveIndicator">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Clave</label>
                                                    <input type="text" class="form-control" wire:model="indClave">
                                                    @error('indClave') <span class="text-danger">{{ $message }}</span> @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Nombre</label>
                                                    <input type="text" class="form-control" wire:model="indNombre">
                                                    @error('indNombre') <span class="text-danger">{{ $message }}</span> @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Método cálculo</label>
                                                    <select class="form-control" wire:model="indCalculationMethod">
                                                        <option value="percent">Porcentual</option>
                                                        <option value="sensitivity">Sensibilidad</option>
                                                        <option value="dichotomic">Dicotómico</option>
                                                    </select>
                                                    @error('indCalculationMethod') <span class="text-danger">{{ $message }}</span> @enderror
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Periodicidad</label>
                                                    <input type="text" class="form-control" wire:model="indPeriodicidad">
                                                    @error('indPeriodicidad') <span class="text-danger">{{ $message }}</span> @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Meta valor</label>
                                                    <input type="text" class="form-control" wire:model="indMetaValor">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Meta operador</label>
                                                    <input type="text" class="form-control" wire:model="indMetaOperador">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Fórmula (JSON)</label>
                                                    <textarea class="form-control" rows="2" wire:model="indFormulaExpression"></textarea>
                                                    @error('indFormulaExpression') <span class="text-danger">{{ $message }}</span> @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Descripción fórmula</label>
                                                    <input type="text" class="form-control" wire:model="indFormulaDescripcion">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Peso (%)</label>
                                                    <input type="number" class="form-control" wire:model="indWeightPercent" step="0.01">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Target valor</label>
                                                    <input type="text" class="form-control" wire:model="indTargetValue">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Target operador</label>
                                                    <input type="text" class="form-control" wire:model="indTargetOperador">
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <button type="submit" class="btn btn-success btn-sm">
                                                {{ $editingIndicatorId ? 'Guardar cambios' : 'Crear indicador' }}
                                            </button>
                                            <button type="button" class="btn btn-default btn-sm" wire:click="cancelIndicatorForm">Cancelar</button>
                                        </div>
                                    </form>
                                </div>
                            @endif

                            @if ($obj->indicators->isEmpty())
                                <p class="text-muted">No hay indicadores para este objetivo.</p>
                            @else
                                <table class="table table-bordered table-condensed">
                                    <thead>
                                        <tr>
                                            <th>Clave</th>
                                            <th>Nombre</th>
                                            <th>Método</th>
                                            <th>Periodicidad</th>
                                            <th>Peso</th>
                                            <th>Target</th>
                                            <th>Fórmula</th>
                                            @if ($canEdit)
                                                <th style="width:80px">Acciones</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($obj->indicators as $ind)
                                            <tr>
                                                <td>{{ $ind->clave }}</td>
                                                <td>{{ $ind->nombre }}</td>
                                                <td>{{ $ind->calculation_method }}</td>
                                                <td>{{ $ind->periodicidad }}</td>
                                                <td>{{ $ind->target?->weight_percent ?? '—' }}</td>
                                                <td>{{ $ind->target?->target_value ? $ind->target->target_value . ' ' . ($ind->target->target_operador ?? '') : '—' }}</td>
                                                <td>{{ $ind->formula ? 'Sí' : 'No' }}</td>
                                                @if ($canEdit)
                                                    <td>
                                                        <button class="btn btn-xs btn-default"
                                                            wire:click="editIndicator({{ $ind->id }})">
                                                            <i class="voyager-edit"></i>
                                                        </button>
                                                        <button class="btn btn-xs btn-danger"
                                                            wire:click="deleteIndicator({{ $ind->id }})"
                                                            wire:confirm="¿Eliminar indicador?">
                                                            <i class="voyager-trash"></i>
                                                        </button>
                                                    </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted">No hay objetivos para este periodo.
                        @if ($canEdit)
                            <button class="btn btn-xs btn-primary" wire:click="createObjective">Crear primer objetivo</button>
                        @endif
                    </p>
                @endforelse
            @endif
        </div>
    </div>
</div>
