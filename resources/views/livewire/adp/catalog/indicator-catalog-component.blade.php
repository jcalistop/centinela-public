<div>
    <style>
        a.adp-catalog-btn,
        a.adp-catalog-btn i,
        a.adp-catalog-btn:hover,
        a.adp-catalog-btn:focus,
        a.adp-catalog-btn:active {
            text-decoration: none !important;
        }
    </style>

    <div class="row" style="margin-bottom: 1rem;">
        <div class="col-md-8">
            <input wire:model.live.debounce.300ms="search" type="text" class="form-control"
                placeholder="Buscar por clave o nombre del indicador..." />
        </div>
        <div class="col-md-4 text-right">
            @if ($canManage)
                <button wire:click="openCreate" class="btn btn-primary">
                    <i class="voyager-plus"></i> Nuevo indicador
                </button>
            @endif
        </div>
    </div>

    <table class="table table-bordered table-hover table-striped">
        <thead>
            <tr>
                <th style="width:120px; white-space:nowrap">Clave</th>
                <th>Nombre</th>
                <th style="width:120px; white-space:nowrap">Método</th>
                <th style="width:80px">Estado</th>
                <th style="width:200px">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr wire:key="item-{{ $item->id }}">
                    <td style="white-space:nowrap"><code>{{ $item->clave }}</code></td>
                    <td>{{ $item->nombre }}</td>
                    <td>{{ $item->calculation_method }}</td>
                    <td>
                        @if ($item->estado)
                            <span class="label label-success">Activo</span>
                        @else
                            <span class="label label-default">Inactivo</span>
                        @endif
                    </td>
                    <td class="text-center" style="white-space:nowrap">
                        @if ($canViewSensitivity)
                            <button wire:click="openSensitivityModal({{ $item->id }})" class="btn btn-xs btn-warning"
                                title="Configurar tabla de sensibilidad">
                                <i class="voyager-graph"></i>
                            </button>
                        @endif
                        @if ($canManage)
                            <button wire:click="openFichaModal({{ $item->id }})" class="btn btn-xs btn-warning"
                                title="Ver ficha del indicador">
                                <i class="voyager-info-circled"></i>
                            </button>
                            <button wire:click="openEdit({{ $item->id }})" class="btn btn-xs btn-info"
                                title="Editar">
                                <i class="voyager-edit"></i>
                            </button>
                            <button wire:click="delete({{ $item->id }})"
                                wire:confirm="¿Seguro que deseas eliminar este ítem del catálogo?"
                                class="btn btn-xs btn-danger" title="Eliminar">
                                <i class="voyager-trash"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted">
                        No hay ítems en el catálogo para tu Servicio de Salud.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Modal crear / editar --}}
    @if ($showModal)
        <div class="modal fade in" style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1"
            role="dialog">
            <div class="modal-dialog modal-lg" role="document" style="margin: 80px auto;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" wire:click="closeModal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title">
                            {{ $editingId ? 'Editar ítem del catálogo' : 'Nuevo ítem del catálogo' }}
                        </h4>
                    </div>
                    <div class="modal-body" style="max-height:calc(100vh - 160px); overflow-y:auto;">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group @error('clave') has-error @enderror">
                                    <label>Clave <span class="text-danger">*</span></label>
                                    <input wire:model="clave" type="text" class="form-control"
                                        placeholder="Ej: ADP-001" />
                                    @error('clave')
                                        <span class="help-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group @error('nombre') has-error @enderror">
                                    <label>Nombre <span class="text-danger">*</span></label>
                                    <input wire:model="nombre" type="text" class="form-control" />
                                    @error('nombre')
                                        <span class="help-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Objetivo</label>
                            <textarea wire:model="objetivo" class="form-control" rows="2"></textarea>
                        </div>

                        <hr />
                        <h5>Meta evaluable</h5>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group @error('metaValor') has-error @enderror">
                                    <label>Valor meta</label>
                                    <input wire:model="metaValor" type="number" step="0.0001" class="form-control"
                                        placeholder="Dejar vacío si no aplica" />
                                    @error('metaValor')
                                        <span class="help-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group @error('metaOperador') has-error @enderror">
                                    <label>Operador</label>
                                    <select wire:model="metaOperador" class="form-control">
                                        <option value="">— Ninguno —</option>
                                        <option value=">=">≥ (mayor o igual)</option>
                                        <option value=">">&#62; (mayor que)</option>
                                        <option value="<=">≤ (menor o igual)</option>
                                        <option value="<">&#60; (menor que)</option>
                                        <option value="=">= (igual a)</option>
                                    </select>
                                    @error('metaOperador')
                                        <span class="help-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label>Descripción de la meta</label>
                                    <input wire:model="metaAnualDescripcion" type="text" class="form-control" />
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group @error('umbralInferior') has-error @enderror">
                                    <label>Umbral inferior (%)</label>
                                    <input wire:model="umbralInferior" type="number" min="0" max="100"
                                        step="0.01" class="form-control" />
                                    @error('umbralInferior')
                                        <span class="help-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group @error('umbralSuperior') has-error @enderror">
                                    <label>Umbral superior (%)</label>
                                    <input wire:model="umbralSuperior" type="number" min="0" max="100"
                                        step="0.01" class="form-control" />
                                    @error('umbralSuperior')
                                        <span class="help-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <hr />
                        <h5>Fórmula y fuentes</h5>

                        <div class="form-group">
                            <label>Descripción de la fórmula</label>
                            <textarea wire:model="formulaDescripcion" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Fuente numerador</label>
                                    <textarea wire:model="fuenteNumerador" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Fuente denominador</label>
                                    <textarea wire:model="fuenteDenominador" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                        </div>

                        <hr />
                        <h5>Configuración técnica</h5>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group @error('periodicidad') has-error @enderror">
                                    <label>Periodicidad <span class="text-danger">*</span></label>
                                    <select wire:model="periodicidad" class="form-control">
                                        <option value="ANUAL">Anual</option>
                                        <option value="SEMESTRAL">Semestral</option>
                                        <option value="TRIMESTRAL">Trimestral</option>
                                    </select>
                                    @error('periodicidad')
                                        <span class="help-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Unidad de medida</label>
                                    <input wire:model="unidadMedida" type="text" class="form-control"
                                        placeholder="Ej: %, casos, días" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group @error('decimales') has-error @enderror">
                                    <label>Decimales</label>
                                    <input wire:model="decimales" type="number" min="0" max="10"
                                        class="form-control" />
                                    @error('decimales')
                                        <span class="help-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group @error('decimalesModo') has-error @enderror">
                                    <label>Modo decimales</label>
                                    <select wire:model="decimalesModo" class="form-control">
                                        <option value="redondear">Redondear</option>
                                        <option value="truncar">Truncar</option>
                                    </select>
                                    @error('decimalesModo')
                                        <span class="help-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group @error('orden') has-error @enderror">
                                    <label>Orden</label>
                                    <input wire:model="orden" type="number" min="0" class="form-control" />
                                    @error('orden')
                                        <span class="help-block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Estado</label>
                                    <select wire:model="estado" class="form-control">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Nivel de aplicación</label>
                            <textarea wire:model="nivelAplicacion" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Método de cálculo</label>
                                    <input wire:model="calculationMethod" type="text" class="form-control" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" wire:click="closeModal">
                            Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="save"
                            wire:loading.attr="disabled">
                            <span wire:loading wire:target="save">
                                <i class="voyager-loader"></i>
                            </span>
                            Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal ficha del indicador --}}
    @if ($showFichaModal && !empty($fichaData))
        <div class="modal fade in" style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1"
            role="dialog">
            <div class="modal-dialog modal-lg" role="document" style="margin: 80px auto;">
                <div class="modal-content">
                    <div class="modal-header" style="background:#f5f5f5; border-bottom:2px solid #ddd;">
                        <button type="button" class="close" wire:click="closeFichaModal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title">
                            <i class="voyager-info-circled"></i>
                            <code style="font-size:15px">{{ $fichaData['clave'] }}</code>
                            {{ $fichaData['nombre'] }}
                            @if ($fichaData['estado'])
                                <span class="label label-success"
                                    style="font-size:11px; vertical-align:middle;">Activo</span>
                            @else
                                <span class="label label-default"
                                    style="font-size:11px; vertical-align:middle;">Inactivo</span>
                            @endif
                        </h4>
                    </div>
                    <div class="modal-body"
                        style="max-height:calc(100vh - 180px); overflow-y:auto; padding:20px 24px;">

                        {{-- Identificación --}}
                        <div class="row" style="margin-bottom:16px;">
                            <div class="col-sm-3">
                                <strong>Periodicidad</strong><br>
                                <span class="label label-info"
                                    style="font-size:12px;">{{ $fichaData['periodicidad'] }}</span>
                            </div>
                            <div class="col-sm-3">
                                <strong>Unidad de medida</strong><br>
                                {{ $fichaData['unidad_medida'] ?: '—' }}
                            </div>
                            <div class="col-sm-3">
                                <strong>Decimales</strong><br>
                                {{ $fichaData['decimales'] }} ({{ $fichaData['decimales_modo'] }})
                            </div>
                            <div class="col-sm-3">
                                <strong>Orden</strong><br>
                                {{ $fichaData['orden'] ?? '—' }}
                            </div>
                        </div>

                        @if ($fichaData['objetivo'])
                            <div style="margin-bottom:14px;">
                                <strong>Objetivo</strong>
                                <p style="margin:4px 0 0; color:#555;">{{ $fichaData['objetivo'] }}</p>
                            </div>
                        @endif

                        <hr style="margin:12px 0;">

                        {{-- Meta --}}
                        <h5 style="margin-bottom:10px; color:#31708f;"><i class="voyager-flag"></i> Meta evaluable</h5>
                        <div class="row" style="margin-bottom:10px;">
                            <div class="col-sm-4">
                                <strong>Valor meta</strong><br>
                                @if ($fichaData['meta_valor'] !== null)
                                    <span style="font-size:15px; font-weight:bold;">
                                        {{ $fichaData['meta_operador'] }}
                                        {{ number_format((float) $fichaData['meta_valor'], $fichaData['decimales']) }}
                                    </span>
                                @else
                                    <span class="text-muted">No definida</span>
                                @endif
                            </div>
                            <div class="col-sm-8">
                                <strong>Descripción</strong><br>
                                {{ $fichaData['meta_anual_descripcion'] ?: '—' }}
                            </div>
                        </div>
                        @if ($fichaData['meta_reglas'])
                            <div style="margin-bottom:10px;">
                                <strong>Reglas especiales</strong>
                                <pre style="font-size:11px; background:#f9f9f9; padding:8px; border-radius:3px; margin:4px 0 0;">{{ $fichaData['meta_reglas'] }}</pre>
                            </div>
                        @endif
                        <div class="row" style="margin-bottom:10px;">
                            <div class="col-sm-6">
                                <strong>Umbral inferior (%)</strong><br>
                                {{ number_format((float) ($fichaData['umbral_inferior'] ?? 0), 2) }}
                            </div>
                            <div class="col-sm-6">
                                <strong>Umbral superior (%)</strong><br>
                                {{ number_format((float) ($fichaData['umbral_superior'] ?? 100), 2) }}
                            </div>
                        </div>

                        @if ($fichaData['nivel_aplicacion'])
                            <div style="margin-bottom:10px;">
                                <strong>Nivel de aplicación</strong>
                                <p style="margin:4px 0 0; color:#555;">{{ $fichaData['nivel_aplicacion'] }}</p>
                            </div>
                        @endif

                        <hr style="margin:12px 0;">

                        {{-- Fórmula --}}
                        <h5 style="margin-bottom:10px; color:#31708f;"><i class="voyager-book"></i> Fórmula y fuentes</h5>
                        @if ($fichaData['formula_descripcion'])
                            <div style="margin-bottom:10px;">
                                <strong>Descripción de la fórmula</strong>
                                <p style="margin:4px 0 0; color:#555;">{{ $fichaData['formula_descripcion'] }}</p>
                            </div>
                        @endif
                        <div class="row" style="margin-bottom:10px;">
                            <div class="col-sm-6">
                                <strong>Fuente numerador</strong>
                                <p style="margin:4px 0 0; color:#555;">{{ $fichaData['fuente_numerador'] ?: '—' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <strong>Fuente denominador</strong>
                                <p style="margin:4px 0 0; color:#555;">{{ $fichaData['fuente_denominador'] ?: '—' }}
                                </p>
                            </div>
                        </div>

                        @if ($fichaData['formula_expression'])
                            <hr style="margin:12px 0;">
                            <div style="margin-bottom:10px;">
                                <strong>Expresión de cálculo</strong>
                                <div class="well well-sm"
                                    style="margin:4px 0 0; font-family:monospace; font-size:13px; background:#f9f9f9;">
                                    {{ $fichaData['formula_expression'] }}
                                </div>
                            </div>
                        @endif

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" wire:click="closeFichaModal">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal sensibilidad --}}
    @if ($showSensitivityModal)
        <div class="modal fade in" style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1"
            role="dialog">
            <div class="modal-dialog modal-lg" role="document" style="margin: 80px auto;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" wire:click="closeSensitivityModal">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title">
                            <i class="voyager-graph"></i> Tabla de sensibilidad
                        </h4>
                    </div>
                    <div class="modal-body">
                        <form wire:submit.prevent="saveSensitivity">
                            <div class="form-group">
                                <label>Nombre de la tabla</label>
                                <input type="text" class="form-control" wire:model="sensitivityTableName"
                                    placeholder="Ej: Tabla de sensibilidad estándar">
                                @error('sensitivityTableName')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            @error('sensitivityRows')
                                <div class="alert alert-danger">{{ $message }}</div>
                            @enderror

                            <table class="table table-bordered table-condensed" style="margin-bottom:8px;">
                                <thead>
                                    <tr>
                                        <th style="width:40px">#</th>
                                        <th>Valor mínimo</th>
                                        <th style="width:100px">Op. mínimo</th>
                                        <th>Valor máximo</th>
                                        <th style="width:100px">Op. máximo</th>
                                        <th>Cumplimiento %</th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sensitivityRows as $i => $row)
                                        <tr>
                                            <td class="text-center text-muted">{{ $i + 1 }}</td>
                                            <td>
                                                <input type="text" class="form-control input-sm"
                                                    wire:model="sensitivityRows.{{ $i }}.valor_minimo">
                                            </td>
                                            <td>
                                                <select class="form-control input-sm"
                                                    wire:model="sensitivityRows.{{ $i }}.operador_minimo">
                                                    <option value=">=">>=</option>
                                                    <option value=">">></option>
                                                    <option value="=">=</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control input-sm"
                                                    wire:model="sensitivityRows.{{ $i }}.valor_maximo">
                                            </td>
                                            <td>
                                                <select class="form-control input-sm"
                                                    wire:model="sensitivityRows.{{ $i }}.operador_maximo">
                                                    <option value="<="><=</option>
                                                    <option value="<"><</option>
                                                    <option value="=">=</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control input-sm"
                                                    wire:model="sensitivityRows.{{ $i }}.cumplimiento">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-xs btn-danger"
                                                    wire:click="removeSensitivityRow({{ $i }})"
                                                    {{ count($sensitivityRows) <= 1 ? 'disabled' : '' }}>
                                                    <i class="voyager-x"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <div class="m-b-10">
                                <button type="button" class="btn btn-xs btn-default" wire:click="addSensitivityRow">
                                    <i class="voyager-plus"></i> Agregar fila
                                </button>
                                <button type="button" class="btn btn-xs btn-danger"
                                    wire:click="clearSensitivity"
                                    wire:confirm="¿Eliminar la configuración de sensibilidad?">
                                    <i class="voyager-trash"></i> Limpiar
                                </button>
                            </div>

                            <div class="modal-footer" style="padding: 15px 0 0 0;">
                                <button type="button" class="btn btn-default" wire:click="closeSensitivityModal">
                                    Cancelar
                                </button>
                                <button type="submit" class="btn btn-success">
                                    Guardar configuración de sensibilidad
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
