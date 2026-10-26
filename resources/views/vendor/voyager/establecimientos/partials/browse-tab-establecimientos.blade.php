@php
    $markers = $browseContext['markers'] ?? [];
    $filterOptions = $browseContext['filterOptions'] ?? [];
    $georef = (int) ($browseContext['georeferencedCount'] ?? 0);
    $sinCoords = (int) ($browseContext['withoutCoordinatesCount'] ?? 0);
    $total = (int) ($browseContext['totalCount'] ?? 0);
    $ssNombre = $browseContext['ssNombre'] ?? null;
    $ssCodigo = $browseContext['ssCodigo'] ?? null;
    $effectiveSsId = $browseContext['effectiveSsId'] ?? null;
    $ssOptions = $browseContext['ssOptions'] ?? [];
    $exploringForeignSs = (bool) ($browseContext['exploringForeignSs'] ?? false);
    $canManageBitacora = (bool) ($browseContext['canManageBitacora'] ?? false);
@endphp
<div class="cen-read-detail cen-browse-establecimientos-panel">
    <div
        class="cen-read-spotlight cen-browse-ss-spotlight @if ($exploringForeignSs) cen-browse-ss-spotlight--exploring @endif">
        <div class="cen-browse-ss-spotlight__head">
            <p class="cen-read-spotlight__label">Servicio de Salud</p>
            @if ($exploringForeignSs)
                <span class="cen-browse-ss-spotlight__badge">Catálogo público</span>
            @endif
        </div>
        <form method="get" action="{{ route('voyager.establecimientos.index') }}" class="cen-browse-ss-form">
            <input type="hidden" name="tab" value="establecimientos">
            <label class="cen-browse-ss-form__label" for="cen-est-ss">Elegir Servicio de Salud</label>
            <select id="cen-est-ss" name="ss" class="cen-browse-ss-select" onchange="this.form.submit()">
                @if ($effectiveSsId === null)
                    <option value="">Seleccione un Servicio de Salud</option>
                @endif
                @foreach ($ssOptions as $option)
                    <option value="{{ $option['id'] }}" @selected((int) $option['id'] === (int) $effectiveSsId)>
                        {{ $option['nombre'] }}
                    </option>
                @endforeach
            </select>
        </form>
        <p class="cen-read-spotlight__hint">
            @if ($ssCodigo)
                Código SS <strong>{{ $ssCodigo }}</strong>
                <span class="cen-browse-ss-spotlight__dot" aria-hidden="true">·</span>
            @elseif ($ssNombre)
                {{ $ssNombre }}
                <span class="cen-browse-ss-spotlight__dot" aria-hidden="true">·</span>
            @endif
            <strong>{{ $georef }}</strong> georeferenciados en mapa,
            <strong>{{ $sinCoords }}</strong> sin coordenadas válidas,
            <strong>{{ $total }}</strong> en total.
        </p>
    </div>

    @if ($total > 0)
        <div class="cen-read-section">
            <div class="cen-read-section__head">
                <i class="voyager-world"></i>
                <h4 class="cen-read-section__title">
                    Mapa de establecimientos
                    @if ($georef > 0)
                        <span class="cen-read-count-badge" id="cen-est-map-title-badge">{{ $georef }}</span>
                    @endif
                </h4>
            </div>

            @if ($georef === 0)
                <div class="cen-read-empty cen-read-empty--compact">
                    <i class="voyager-location"></i>
                    <p><strong>Sin puntos en el mapa</strong></p>
                    <p style="font-size:13px;max-width:420px;margin:0 auto;">
                        No hay establecimientos con latitud y longitud válidas. Use los filtros y el listado inferior.
                    </p>
                </div>
                <div class="cen-browse-establecimientos-filters-standalone" id="cen-est-filters-root"
                    data-communes='@json($filterOptions['communes'] ?? [])'>
                    @include('voyager::establecimientos.partials.browse-establecimientos-filters')
                    @if ($canManageBitacora)
                        <div class="cen-browse-establecimientos-filters-pendiente">
                            <button type="button"
                                class="cen-browse-establecimientos-legend__toggle cen-est-pendiente-toggle"
                                aria-pressed="false" title="Mostrar solo establecimientos con tarea pendiente">
                                <span
                                    class="cen-browse-establecimientos-legend__dot cen-browse-establecimientos-legend__dot--pending"></span>
                                Solo tareas pendientes
                            </button>
                        </div>
                    @endif
                </div>
            @else
                <div class="cen-browse-establecimientos-layout" id="cen-est-map-root"
                    data-markers='@json($markers)' data-communes='@json($filterOptions['communes'] ?? [])'>
                    <div class="cen-browse-establecimientos-layout__map">
                        <div id="cen-est-map" class="cen-browse-establecimientos" role="application"
                            aria-label="Mapa de establecimientos georeferenciados"></div>
                        <div class="cen-browse-establecimientos-legend" role="group"
                            aria-label="Leyenda y filtros rápidos del mapa">
                            <span class="cen-browse-establecimientos-legend__item">
                                <span
                                    class="cen-browse-establecimientos-legend__dot cen-browse-establecimientos-legend__dot--default"></span>
                                Establecimiento
                            </span>
                            @if ($canManageBitacora)
                                <button type="button"
                                    class="cen-browse-establecimientos-legend__toggle cen-est-pendiente-toggle"
                                    aria-pressed="false" title="Mostrar solo establecimientos con tarea pendiente">
                                    <span
                                        class="cen-browse-establecimientos-legend__dot cen-browse-establecimientos-legend__dot--pending"></span>
                                    Solo tareas pendientes
                                </button>
                            @endif
                        </div>
                    </div>
                    @include('voyager::establecimientos.partials.browse-establecimientos-filters')
                </div>
            @endif
        </div>

        @include('voyager::establecimientos.partials.browse-establecimientos-list')
    @else
        <div class="cen-read-section">
            <div class="cen-read-empty">
                <i class="voyager-shop"></i>
                <p><strong>Sin establecimientos</strong></p>
                <p style="font-size:13px;">
                    @if ($effectiveSsId === null)
                        Seleccione un Servicio de Salud para ver el catálogo.
                    @else
                        No hay registros visibles en el catálogo para este Servicio de Salud.
                    @endif
                </p>
            </div>
        </div>
    @endif
</div>
