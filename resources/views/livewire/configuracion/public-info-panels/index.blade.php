<div>
    <div class="page-content container-fluid">
        <h1 class="page-title">
            <i class="voyager-documentation"></i> Paneles públicos
        </h1>

        <div class="panel panel-bordered">
            <div class="panel-body">
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-md-4">
                        <input type="search" class="form-control" placeholder="Buscar por título o slug"
                            wire:model.live.debounce.300ms="search">
                    </div>
                    <div class="col-md-8 text-right">
                        @if (count($selected) > 0)
                            <button type="button" class="btn btn-danger" wire:click="confirmDeleteSelected">
                                <i class="voyager-trash"></i> Eliminar seleccionados ({{ count($selected) }})
                            </button>
                        @endif
                        <button type="button" class="btn btn-default" wire:click="openAgentGuide">
                            Contratos para agentes
                        </button>
                        <button type="button" class="btn btn-default" wire:click="openInventory">
                            Copiar inventario
                        </button>
                        <a href="{{ route('centinela.admin.paneles-publicos.create') }}" class="btn btn-success">
                            <i class="voyager-plus"></i> Nuevo panel
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 30px;"></th>
                                <th>Título</th>
                                <th>Versión</th>
                                <th>Tipo</th>
                                <th>Publicado</th>
                                <th style="width: 90px;">Orden</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($panels as $panel)
                                <tr wire:key="panel-{{ $panel->id }}">
                                    <td>
                                        <input type="checkbox" value="{{ $panel->id }}" wire:model.live="selected">
                                    </td>
                                    <td>
                                        <a href="{{ route('centinela.admin.paneles-publicos.show', ['panel' => $panel->id]) }}">
                                            {{ $panel->titulo }}
                                        </a>
                                        <div class="text-muted" style="font-size: 12px;">{{ $panel->slug }}</div>
                                    </td>
                                    <td>{{ $panel->version_panel ?: '—' }}</td>
                                    <td>
                                        <span class="label label-default">
                                            {{ $panel->isEmbedExterno() ? 'Embed' : 'HTML' }}
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button"
                                            class="btn btn-xs {{ $panel->publicado ? 'btn-success' : 'btn-default' }}"
                                            wire:click="togglePublicado({{ $panel->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="togglePublicado({{ $panel->id }})"
                                            title="{{ $panel->publicado ? 'Despublicar' : 'Publicar' }}">
                                            {{ $panel->publicado ? 'Publicado' : 'Borrador' }}
                                        </button>
                                    </td>
                                    <td>
                                        @if (trim($search) === '')
                                            <button type="button" class="btn btn-xs btn-default"
                                                wire:click="moveUp({{ $panel->id }})" title="Subir">&uarr;</button>
                                            <button type="button" class="btn btn-xs btn-default"
                                                wire:click="moveDown({{ $panel->id }})" title="Bajar">&darr;</button>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right" style="white-space: nowrap;">
                                        @if ($panel->hasRequiredAssets())
                                            <button type="button" class="btn btn-xs btn-default"
                                                wire:click="openShare({{ $panel->id }})">
                                                {{ $panel->publicado ? 'Compartir panel' : 'Compartir revisión' }}
                                            </button>
                                        @endif
                                        <a href="{{ route('centinela.admin.paneles-publicos.show', ['panel' => $panel->id]) }}"
                                            class="btn btn-xs btn-warning">
                                            <i class="voyager-eye"></i> Ver
                                        </a>
                                        <a href="{{ route('centinela.admin.paneles-publicos.edit', ['panel' => $panel->id]) }}"
                                            class="btn btn-xs btn-primary">
                                            <i class="voyager-edit"></i> Editar
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        {{ trim($search) === '' ? 'Sin paneles registrados.' : 'Sin resultados para la búsqueda.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($showInventory && $inventory !== null)
        @include('livewire.configuracion.public-info-panels.partials.inventory-modal', ['inventory' => $inventory])
    @endif

    @if ($showShare && $sharePanel !== null)
        @include('livewire.configuracion.public-info-panels.partials.share-modal', ['sharePanel' => $sharePanel])
    @endif

    @if ($showAgentGuide)
        @include('livewire.configuracion.public-info-panels.partials.agent-guide-modal')
    @endif

    @if ($showDeleteConfirm)
        <div class="modal fade in" style="display:block; background:rgba(0,0,0,0.5); overflow-y:auto;" tabindex="-1"
            role="dialog">
            <div class="modal-dialog" role="document" style="margin: 80px auto;">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" wire:click="cancelDeleteSelected">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title">Eliminar paneles</h4>
                    </div>
                    <div class="modal-body">
                        <p>¿Eliminar {{ count($selected) }} panel(es) seleccionado(s)? Esta acción no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" wire:click="cancelDeleteSelected">Cancelar</button>
                        <button type="button" class="btn btn-danger" wire:click="deleteSelected">Eliminar</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
