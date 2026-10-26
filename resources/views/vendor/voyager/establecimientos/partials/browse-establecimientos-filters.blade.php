@php
    $filterOptions = $browseContext['filterOptions'] ?? [];
    $georef = (int) ($browseContext['georeferencedCount'] ?? 0);
@endphp
<aside class="cen-browse-establecimientos-filters" aria-label="Filtros de establecimientos">
    <h5 class="cen-browse-establecimientos-filters__title">
        <i class="voyager-filter"></i>
        Filtros
    </h5>
    <form id="cen-est-filters" class="cen-browse-establecimientos-filters__form" autocomplete="off">
        <div class="form-group cen-browse-establecimientos-filters__establishments">
            <label for="cen-est-filter-establecimientos">Nombre de establecimiento</label>
            <select id="cen-est-filter-establecimientos" class="form-control select2 cen-est-establishments-select"
                multiple data-filter="codigos" data-placeholder="Buscar y seleccionar establecimientos…">
                @foreach ($filterOptions['establishments'] ?? [] as $establishment)
                    <option value="{{ $establishment['codigo'] }}">{{ $establishment['label'] }}</option>
                @endforeach
            </select>
            <p class="help-block">Puede elegir uno o más establecimientos.</p>
        </div>
        <div class="form-group">
            <label for="cen-est-filter-tipo">Tipo de establecimiento</label>
            <select id="cen-est-filter-tipo" class="form-control" data-filter="tipo">
                <option value="">Todos</option>
                @foreach ($filterOptions['tipo'] ?? [] as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="cen-est-filter-adm">Dependencia administrativa</label>
            <select id="cen-est-filter-adm" class="form-control" data-filter="admDependency">
                <option value="">Todas</option>
                @foreach ($filterOptions['admDependency'] ?? [] as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="cen-est-filter-nivel">Nivel de atención</label>
            <select id="cen-est-filter-nivel" class="form-control" data-filter="nivelAtencion">
                <option value="">Todos</option>
                @foreach ($filterOptions['nivelAtencion'] ?? [] as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="cen-est-filter-urgencia">Tiene servicio Urgencia</label>
            <select id="cen-est-filter-urgencia" class="form-control" data-filter="tieneUrgencia">
                <option value="">Todos</option>
                @foreach ($filterOptions['tieneUrgencia'] ?? [] as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="cen-est-filter-provincia">Provincia</label>
            <select id="cen-est-filter-provincia" class="form-control" data-filter="provinceId">
                <option value="">Todas</option>
                @foreach ($filterOptions['provinces'] ?? [] as $province)
                    <option value="{{ $province['id'] }}">{{ $province['nombre'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="cen-est-filter-comuna">Comuna</label>
            <select id="cen-est-filter-comuna" class="form-control" data-filter="communeId" disabled>
                <option value="">Todas</option>
            </select>
        </div>
        <button type="button" class="btn btn-default btn-sm btn-block" id="cen-est-filters-reset">
            Limpiar filtros
        </button>
    </form>
    <p class="cen-browse-establecimientos-filters__count">
        <strong id="cen-est-map-visible-count">{{ $georef }}</strong>
        de <span id="cen-est-map-total-count">{{ $georef }}</span> en el mapa
    </p>
</aside>
