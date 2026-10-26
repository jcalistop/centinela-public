@php
    $center = $dataTypeContent;
    $addr = $center->cenAddress;
    $commune = $addr?->cenCommune;
    $province = $commune?->cenProvince;
    $region = $province?->cenRegion;
    $direccionLinea = $addr ? trim(($addr->direccion ?? '') . ($addr->numero ? ' #' . $addr->numero : '')) : '';
    $hasValidCoords = false;
    $mapLat = null;
    $mapLng = null;

    if ($addr !== null && $addr->latitud !== null && $addr->longitud !== null) {
        $mapLat = (float) $addr->latitud;
        $mapLng = (float) $addr->longitud;
        $hasValidCoords = $mapLat >= -56.0 && $mapLat <= -17.0 && $mapLng >= -76.0 && $mapLng <= -66.0;
    }

    $mapLabel = $center->nombre_corto ?? ($center->nombre_oficial ?? $center->codigo);
@endphp
<div class="cen-read-detail">
    @if ($addr === null)
        <div class="cen-read-section">
            <div class="cen-read-empty">
                <i class="voyager-location"></i>
                <p><strong>Sin dirección registrada</strong></p>
                <p class="text-muted" style="max-width:420px;margin:0 auto;font-size:13px;">
                    La comuna del catálogo nacional no está en Centinela o el registro no trae datos de ubicación.
                </p>
            </div>
        </div>
    @else
        @if ($region || $province || $commune)
            <div class="cen-read-spotlight">
                <p class="cen-read-spotlight__label">Territorio</p>
                <ul class="cen-read-breadcrumb">
                    @if ($region)
                        <li>{{ $region->nombre }}</li>
                    @endif
                    @if ($province)
                        <li>{{ $province->nombre }}</li>
                    @endif
                    @if ($commune)
                        <li>{{ $commune->nombre }}</li>
                    @endif
                </ul>
            </div>
        @endif

        <div class="cen-read-ubicacion-row">
            <div class="cen-read-ubicacion-row__col">
                @include('voyager::establecimientos.partials.read-detail-section', [
                    'title' => 'Dirección postal',
                    'icon' => 'voyager-location',
                    'items' => [
                        [
                            'label' => 'Dirección',
                            'value' => $direccionLinea ?: null,
                            'wide' => true,
                            'highlight' => (bool) $direccionLinea,
                        ],
                        ['label' => 'Tipo de vía', 'value' => $addr->via],
                    ],
                ])
            </div>
            <div class="cen-read-ubicacion-row__col">
                @include('voyager::establecimientos.partials.read-detail-section', [
                    'title' => 'Contacto y georreferencia',
                    'icon' => 'voyager-world',
                    'twoCol' => true,
                    'items' => [
                        ['label' => 'Teléfono', 'value' => $addr->telefono],
                        ['label' => 'Latitud', 'value' => $addr->latitud],
                        ['label' => 'Longitud', 'value' => $addr->longitud],
                    ],
                ])
            </div>
        </div>

        @if ($hasValidCoords)
            <div class="cen-read-section cen-read-ubicacion-map-section">
                <div class="cen-read-section__head">
                    <i class="voyager-location"></i>
                    <h4 class="cen-read-section__title">Mapa de ubicación</h4>
                </div>
                <div class="cen-read-section__body cen-read-ubicacion-map-section__body">
                    <div id="cen-read-ubicacion-map" class="cen-read-ubicacion-map" role="application"
                        aria-label="Mapa de ubicación del establecimiento" data-lat="{{ $mapLat }}"
                        data-lng="{{ $mapLng }}" data-label="{{ e($mapLabel) }}"
                        data-codigo="{{ e($center->codigo) }}"></div>
                    <p class="cen-read-ubicacion-map__hint text-muted">
                        <i class="voyager-eye"></i>
                        Coordenadas según catálogo nacional ({{ number_format($mapLat, 6, '.', '') }},
                        {{ number_format($mapLng, 6, '.', '') }}).
                    </p>
                </div>
            </div>
        @elseif ($addr->latitud !== null || $addr->longitud !== null)
            <p class="cen-read-ubicacion-map__unavailable text-muted">
                <i class="voyager-warning"></i>
                Las coordenadas registradas no son válidas para mostrar en el mapa (fuera del rango territorial de Chile
                o incompletas).
            </p>
        @else
            <p class="cen-read-ubicacion-map__unavailable text-muted">
                <i class="voyager-eye"></i>
                Sin georreferenciación en el catálogo nacional para este establecimiento.
            </p>
        @endif
    @endif
</div>
