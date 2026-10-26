<div class="metas-instrument-ui">
    @if (session()->has('units_message'))
        <div class="alert alert-success alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            {{ session('units_message') }}
        </div>
    @endif

    @if (session()->has('units_error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            {{ session('units_error') }}
        </div>
    @endif

    @if (session()->has('units_info'))
        <div class="alert alert-info alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
            <i class="voyager-info-circled"></i> {{ session('units_info') }}
        </div>
    @endif

    @if ($isCerrado)
        <div class="alert alert-warning">
            <i class="voyager-lock"></i>
            El instrumento está <strong>CERRADO</strong>. No es posible crear, editar ni eliminar unidades.
        </div>
    @endif

    <p class="text-muted" style="margin-bottom: 1rem;">
        Cada unidad representa un servicio clínico u homólogo bajo el establecimiento. Los datos y el convenio se
        parametrizan por unidad.
    </p>

    @unless ($isCerrado)
        <x-centinela.compass.dashboard-block title="Nueva unidad" icon="voyager-plus" style="margin-bottom: 1rem;">
            <x-slot:actions>
                <span class="label label-info">control de gestión</span>
            </x-slot:actions>
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group @error('newCodigo') has-error @enderror">
                        <label>Código</label>
                        <input type="text" class="form-control" wire:model="newCodigo" maxlength="64"
                            placeholder="ej. URG-1">
                        @error('newCodigo')
                            <span class="help-block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group @error('newNombre') has-error @enderror">
                        <label>Nombre (opcional)</label>
                        <input type="text" class="form-control" wire:model="newNombre" maxlength="200">
                        @error('newNombre')
                            <span class="help-block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group @error('newMetUnitTypeId') has-error @enderror">
                        <label>Tipo</label>
                        <select class="form-control" wire:model="newMetUnitTypeId">
                            <option value="">— Seleccione —</option>
                            @foreach ($unitTypes as $t)
                                <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                            @endforeach
                        </select>
                        @error('newMetUnitTypeId')
                            <span class="help-block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-sm-2" style="padding-top: 24px;">
                    <button type="button" class="btn btn-primary btn-block" wire:click="saveNewUnit"
                        wire:loading.attr="disabled">
                        <span wire:loading wire:target="saveNewUnit"><i class="voyager-loader"></i></span>
                        Crear
                    </button>
                </div>
            </div>
            @if ($hasEnviadoAgreement ?? false)
                <div class="alert alert-warning" style="margin-top: 1rem; margin-bottom: 0;">
                    <p>
                        Este establecimiento tiene convenio <strong>enviado</strong>. Crear una unidad obliga a volver a
                        <strong>Formulación</strong> (alcance: todo el SS) y deja solo este centro en borrador.
                    </p>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" wire:model="confirmCreateDespiteEnviado">
                            Confirmo la creación y el retroceso de etapa
                        </label>
                    </div>
                    <div class="form-group @error('unitCreationMotivo') has-error @enderror" style="margin-bottom: 0;">
                        <label>Motivo del retroceso</label>
                        <textarea class="form-control" rows="2" wire:model="unitCreationMotivo"></textarea>
                        @error('unitCreationMotivo')
                            <span class="help-block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            @endif
        </x-centinela.compass.dashboard-block>
    @endunless

    @if ($units->isEmpty())
        <div class="alert alert-warning" style="margin-bottom: 0;">
            No hay unidades registradas para este establecimiento en el año activo. Cree al menos una unidad para
            cargar datos Ley 20.707.
        </div>
    @else
        <div class="table-responsive">
            <table
                class="table table-bordered table-striped metas-instrument-table cen-compass-table metas-units-manager-table"
                style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th class="metas-units-col--codigo">Código</th>
                        <th class="metas-units-col--nombre">Nombre</th>
                        <th class="metas-units-col--tipo">Tipo</th>
                        <th class="text-center metas-units-col--activa">Activa</th>
                        <th class="text-center metas-units-col--accion">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($units as $u)
                        <tr wire:key="unit-{{ $u->id }}">
                            @if ($editingUnitId === $u->id)
                                <td class="metas-units-col--codigo">
                                    <div class="form-group @error('editCodigo') has-error @enderror" style="margin: 0;">
                                        <input type="text" class="form-control input-sm" wire:model="editCodigo"
                                            maxlength="64">
                                        @error('editCodigo')
                                            <span class="help-block">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </td>
                                <td class="metas-units-col--nombre">
                                    <div class="form-group @error('editNombre') has-error @enderror" style="margin: 0;">
                                        <input type="text" class="form-control input-sm" wire:model="editNombre"
                                            maxlength="200">
                                        @error('editNombre')
                                            <span class="help-block">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </td>
                                <td class="metas-units-col--tipo">
                                    <div class="form-group @error('editMetUnitTypeId') has-error @enderror"
                                        style="margin: 0;">
                                        <select class="form-control input-sm" wire:model="editMetUnitTypeId">
                                            @foreach ($editUnitTypes as $t)
                                                <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                                            @endforeach
                                        </select>
                                        @error('editMetUnitTypeId')
                                            <span class="help-block">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </td>
                                <td class="text-center metas-units-col--activa">
                                    @if ($u->activo)
                                        <span class="label label-success">Sí</span>
                                    @else
                                        <span class="label label-default">No</span>
                                    @endif
                                </td>
                                <td class="text-center metas-units-col--accion">
                                    <button type="button" class="btn btn-xs btn-primary" wire:click="updateUnit"
                                        wire:loading.attr="disabled" wire:target="updateUnit">
                                        Guardar
                                    </button>
                                    <button type="button" class="btn btn-xs btn-default" wire:click="cancelEdit">
                                        Cancelar
                                    </button>
                                </td>
                            @else
                                <td class="metas-units-col--codigo"><code>{{ $u->codigo }}</code></td>
                                <td class="metas-units-col--nombre">{{ $u->nombre ?? '—' }}</td>
                                <td class="metas-units-col--tipo">{{ $u->unitType?->nombre ?? '—' }}</td>
                                <td class="text-center metas-units-col--activa">
                                    @if ($isCerrado || $pendingDeleteUnitId === $u->id)
                                        @if ($u->activo)
                                            <span class="label label-success">Sí</span>
                                        @else
                                            <span class="label label-default">No</span>
                                        @endif
                                    @else
                                        <label class="switch-toggle metas-units-activo-toggle"
                                            style="margin-bottom: 0;">
                                            <input type="checkbox" @checked($u->activo)
                                                wire:click="toggleActivo({{ $u->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="toggleActivo({{ $u->id }})"
                                                aria-label="Unidad {{ $u->codigo }} activa">
                                            <span class="switch-slider"></span>
                                        </label>
                                    @endif
                                </td>
                                <td class="text-center metas-units-col--accion">
                                    @unless ($isCerrado)
                                        @if ($pendingDeleteUnitId === $u->id)
                                            <span class="text-danger"
                                                style="display: block; margin-bottom: 4px; font-size: 0.85em;">
                                                ¿Eliminar <strong>{{ $u->codigo }}</strong>?
                                            </span>
                                            <button type="button" class="btn btn-xs btn-danger" wire:click="deleteUnit"
                                                wire:loading.attr="disabled" wire:target="deleteUnit">
                                                Confirmar
                                            </button>
                                            <button type="button" class="btn btn-xs btn-default"
                                                wire:click="cancelDelete">
                                                Cancelar
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-xs btn-primary"
                                                wire:click="startEdit({{ $u->id }})" wire:loading.attr="disabled"
                                                wire:target="startEdit({{ $u->id }})" title="Editar"
                                                aria-label="Editar {{ $u->codigo }}">
                                                <span wire:loading wire:target="startEdit({{ $u->id }})"><i
                                                        class="voyager-loader"></i></span>
                                                <i class="voyager-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-xs btn-danger"
                                                wire:click="confirmDeleteUnit({{ $u->id }})"
                                                wire:loading.attr="disabled" title="Eliminar"
                                                aria-label="Eliminar {{ $u->codigo }}">
                                                <i class="voyager-trash"></i>
                                            </button>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endunless
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
