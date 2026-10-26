<div>
    <div class="panel panel-bordered">
        <div class="panel-heading">
            <p class="panel-title">
                <i class="voyager-people"></i> Asignaciones persona-cargo por Servicio de Salud
            </p>
            @if ($canEdit)
                <div class="panel-actions">
                    <button class="btn btn-sm btn-primary" wire:click="create">
                        <i class="voyager-plus"></i> Nueva asignación
                    </button>
                </div>
            @endif
        </div>
        <div class="panel-body">
            <div class="row m-b-10">
                <div class="col-md-4">
                    <input type="text" class="form-control" placeholder="Buscar por usuario o cargo..."
                        wire:model.live.debounce.300ms="search">
                </div>
            </div>

            @if ($showForm)
                <div class="well m-b-20">
                    <h4>{{ $editId ? 'Editar asignación' : 'Nueva asignación' }}</h4>
                    <form wire:submit.prevent="save" class="form-horizontal">
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Usuario</label>
                            <div class="col-sm-9">
                                <select class="form-control" wire:model="user_id">
                                    <option value="">Seleccionar usuario...</option>
                                    @foreach ($users as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Cargo</label>
                            <div class="col-sm-9">
                                <select class="form-control" wire:model="adp_position_id">
                                    <option value="">Seleccionar cargo...</option>
                                    @foreach ($positions as $p)
                                        <option value="{{ $p->id }}">{{ $p->codigo }} — {{ $p->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('adp_position_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Establecimiento</label>
                            <div class="col-sm-9">
                                <select class="form-control" wire:model="cen_center_id">
                                    <option value="">Seleccionar establecimiento...</option>
                                    @foreach ($centers as $c)
                                        <option value="{{ $c->id }}">{{ $c->nombre_corto }}</option>
                                    @endforeach
                                </select>
                                @error('cen_center_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Fecha inicio</label>
                            <div class="col-sm-4">
                                <input type="date" class="form-control" wire:model="start_date">
                                @error('start_date')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">Fecha término</label>
                            <div class="col-sm-4">
                                <input type="date" class="form-control" wire:model="end_date">
                                @error('end_date')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="col-sm-offset-3 col-sm-9">
                                <button type="submit" class="btn btn-success">
                                    {{ $editId ? 'Guardar cambios' : 'Crear' }}
                                </button>
                                <button type="button" class="btn btn-default" wire:click="cancelForm">Cancelar</button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Cargo</th>
                        <th>Establecimiento</th>
                        <th>Inicio</th>
                        <th>Término</th>
                        @if ($canEdit)
                            <th style="width:120px">Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $ass)
                        <tr>
                            <td>{{ $ass->user?->name ?? '—' }}</td>
                            <td>{{ $ass->position?->nombre ?? '—' }}</td>
                            <td>{{ $ass->center?->nombre_corto ?? '—' }}</td>
                            <td>{{ $ass->start_date?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $ass->end_date?->format('Y-m-d') ?? '—' }}</td>
                            @if ($canEdit)
                                <td>
                                    <button class="btn btn-xs btn-default" wire:click="edit({{ $ass->id }})">
                                        <i class="voyager-edit"></i>
                                    </button>
                                    <button class="btn btn-xs btn-danger" wire:click="delete({{ $ass->id }})"
                                        wire:confirm="¿Eliminar asignación?">
                                        <i class="voyager-trash"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canEdit ? 6 : 5 }}" class="text-muted text-center">No hay asignaciones
                                registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{ $assignments->links() }}
        </div>
    </div>
</div>
