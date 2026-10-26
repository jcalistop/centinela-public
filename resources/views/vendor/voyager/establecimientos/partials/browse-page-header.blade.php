@php
    use App\Support\Cen\CenEstablishmentsUiCopy;
@endphp
<div class="cen-browse-header container-fluid @if (empty($showBrowseToolbar)) cen-browse-header--no-toolbar @endif">
    <div class="cen-browse-header__top">
        <div class="cen-browse-header__title-block">
            <h1 class="page-title cen-browse-header__title">
                <i class="{{ $dataType->icon }}"></i>
                {{ $dataType->getTranslatedAttribute('display_name_plural') }}
            </h1>
            <p class="cen-browse-header__subtitle">
                Catálogo nacional (datos.gob.cl) y establecimientos en Centinela
            </p>
        </div>
        <div class="cen-browse-header__locale">
            @include('voyager::multilingual.language-selector')
        </div>
    </div>

    @if (!empty($showBrowseToolbar))
        <div class="cen-browse-header__toolbar" role="toolbar" aria-label="Acciones del listado">
            @if (!empty($canSyncServicioSalud) || !empty($canSyncNationalFull))
                <div class="cen-browse-toolbar-group">
                    <span class="cen-browse-toolbar-label">
                        Catálogo nacional (datos.gob.cl)
                        @include('voyager::establecimientos.partials.help-icon', [
                            'text' => CenEstablishmentsUiCopy::HELP_CATALOGO_NACIONAL,
                        ])
                    </span>
                    <div class="cen-browse-toolbar-actions">
                        @if (!empty($canSyncServicioSalud) && empty($exploringForeignSs))
                            <form method="post" action="{{ route('voyager.establecimientos.sync-national') }}"
                                class="cen-browse-inline-form"
                                onsubmit="var b=this.querySelector('button[type=submit]'); if(b.disabled) return false; b.disabled=true;">
                                @csrf
                                <button type="submit" class="btn btn-info"
                                    @if (!empty($syncInProgress)) disabled
                                title="Ya hay una sincronización en curso para su Servicio de Salud" @endif
                                    onclick="if(this.disabled){return false;} return confirm('¿Iniciar actualización del catálogo nacional para su Servicio de Salud? El proceso corre en segundo plano y puede tardar unos minutos.');">
                                    <i class="voyager-cloud-download"></i>
                                    @if (!empty($syncInProgress))
                                        SS: sincronización en curso…
                                    @else
                                        Sincronizar catálogo (SS)
                                    @endif
                                </button>
                            </form>
                        @endif
                        @if (!empty($canSyncNationalFull))
                            <form method="post" action="{{ route('voyager.establecimientos.sync-national-full') }}"
                                class="cen-browse-inline-form"
                                onsubmit="var b=this.querySelector('button[type=submit]'); if(b.disabled) return false; b.disabled=true;">
                                @csrf
                                <button type="submit" class="btn btn-warning"
                                    @if (!empty($syncFullInProgress)) disabled
                                title="Ya hay una sincronización nacional completa en curso" @endif
                                    onclick="if(this.disabled){return false;} return confirm('¿Iniciar actualización del catálogo nacional para TODOS los Servicios de Salud? Solo administradores. Puede tardar varios minutos (misma tarea programada semanal del sistema).');">
                                    <i class="voyager-world"></i>
                                    @if (!empty($syncFullInProgress))
                                        Nacional: en curso…
                                    @else
                                        Sincronizar nacional
                                    @endif
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            @can('add', app($dataType->model_name))
                <div class="cen-browse-toolbar-group">
                    <span class="cen-browse-toolbar-label">Registros</span>
                    <div class="cen-browse-toolbar-actions">
                        @if (empty($exploringForeignSs))
                            <a href="{{ route('voyager.' . $dataType->slug . '.create') }}" class="btn btn-success">
                                <i class="voyager-plus"></i>
                                <span>Alta manual</span>
                            </a>
                        @endif
                        @can('edit', app($dataType->model_name))
                            @if (!empty($dataType->order_column) && !empty($dataType->order_display_column))
                                <a href="{{ route('voyager.' . $dataType->slug . '.order') }}" class="btn btn-default">
                                    <i class="voyager-list"></i>
                                    <span>{{ __('voyager::bread.order') }}</span>
                                </a>
                            @endif
                        @endcan
                    </div>
                </div>
            @else
                @can('edit', app($dataType->model_name))
                    @if (!empty($dataType->order_column) && !empty($dataType->order_display_column))
                        <div class="cen-browse-toolbar-group">
                            <span class="cen-browse-toolbar-label">Registros</span>
                            <div class="cen-browse-toolbar-actions">
                                <a href="{{ route('voyager.' . $dataType->slug . '.order') }}" class="btn btn-default">
                                    <i class="voyager-list"></i>
                                    <span>{{ __('voyager::bread.order') }}</span>
                                </a>
                            </div>
                        </div>
                    @endif
                @endcan
            @endcan
        </div>
    @endif
</div>
