<div>
    <div class="panel panel-bordered">
        <div class="panel-heading">
            <p class="panel-title">
                <i class="voyager-group"></i> Catálogo global de cargos ADP
            </p>
            @if ($canEdit)
                <div class="panel-actions">
                    <button class="btn btn-sm btn-primary" wire:click="create">
                        <i class="voyager-plus"></i> Nuevo cargo
                    </button>
                </div>
            @endif
        </div>
        <div class="panel-body">
            <div class="row m-b-10">
                <div class="col-md-4">
                    <input type="text" class="form-control" placeholder="Buscar cargo..."
                        wire:model.live.debounce.300ms="search">
                </div>
            </div>

            @if ($showForm)
                <div class="well m-b-20">
                    <h4>{{ $editId ? 'Editar cargo' : 'Nuevo cargo' }}</h4>
                    <form wire:submit.prevent="save">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Código</label>
                                    <input type="text" class="form-control" wire:model="codigo">
                                    @error('codigo') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="form-group">
                                    <label>Nombre</label>
                                    <input type="text" class="form-control" wire:model="nombre">
                                    @error('nombre') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <textarea class="form-control" rows="2" wire:model="descripcion"></textarea>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">{{ $editId ? 'Guardar cambios' : 'Crear' }}</button>
                            <button type="button" class="btn btn-default" wire:click="cancelForm">Cancelar</button>
                        </div>
                    </form>
                </div>
            @endif

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        @if ($canEdit)
                            <th style="width:120px">Acciones</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($positions as $pos)
                        <tr>
                            <td>{{ $pos->codigo }}</td>
                            <td>{{ $pos->nombre }}</td>
                            <td>{{ $pos->descripcion ?? '—' }}</td>
                            @if ($canEdit)
                                <td>
                                    <button class="btn btn-xs btn-default" wire:click="edit({{ $pos->id }})">
                                        <i class="voyager-edit"></i>
                                    </button>
                                    <button class="btn btn-xs btn-danger" wire:click="delete({{ $pos->id }})"
                                        wire:confirm="¿Eliminar cargo?">
                                        <i class="voyager-trash"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canEdit ? 4 : 3 }}" class="text-muted text-center">No hay cargos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{ $positions->links() }}
        </div>
    </div>
</div>
