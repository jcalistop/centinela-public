@php
    $center = $dataTypeContent;
    $estadoVal = $center->estado_funcionamiento?->value ?? '';
    $estadoBadge = match (true) {
        str_contains($estadoVal, 'Vigente') => 'success',
        str_contains($estadoVal, 'Cierre') || $estadoVal === 'Cerrado' => 'danger',
        $estadoVal === 'Desconocido' || $estadoVal === '' => 'neutral',
        default => 'warning',
    };
    $estadoPillClass = match ($estadoBadge) {
        'success' => 'cen-read-status-pill--success',
        'danger' => 'cen-read-status-pill--danger',
        'warning' => 'cen-read-status-pill--warning',
        default => 'cen-read-status-pill--neutral',
    };
    $urgenciaVal = $center->tiene_urgencia?->value ?? '';
    $urgenciaBadge = match ($urgenciaVal) {
        'Sí', 'SI' => 'success',
        'No' => 'neutral',
        default => 'neutral',
    };
@endphp
<div class="cen-read-detail">
    <div class="cen-read-section" style="margin-bottom:18px;">
        <div class="cen-read-section__body" style="padding:16px 18px;">
            <span class="cen-read-item__label">Estado de funcionamiento</span>
            <div style="margin-top:8px;">
                <span class="cen-read-status-pill {{ $estadoPillClass }}">
                    <i class="voyager-params"></i>
                    {{ $estadoVal !== '' ? $estadoVal : '—' }}
                </span>
            </div>
        </div>
    </div>

    @include('voyager::establecimientos.partials.read-detail-section', [
        'title' => 'Niveles y tipo de atención',
        'icon' => 'voyager-bar-chart',
        'twoCol' => true,
        'items' => [
            ['label' => 'Nivel de atención', 'value' => $center->nivel_atencion, 'badge' => 'info'],
            ['label' => 'Nivel de complejidad', 'value' => $center->nivel_complejidad],
            ['label' => 'Tipo de atención', 'value' => $center->tipo_atencion],
            ['label' => 'Tipo prestador', 'value' => $center->cen_provider_type],
        ],
    ])

    @include('voyager::establecimientos.partials.read-detail-section', [
        'title' => 'Urgencia y SAPU',
        'icon' => 'voyager-activity',
        'twoCol' => true,
        'items' => [
            [
                'label' => 'Tiene servicio de urgencia',
                'value' => $center->tiene_urgencia,
                'badge' => $urgenciaBadge,
            ],
            ['label' => 'Tipo de urgencia', 'value' => $center->cen_emergency_type],
            ['label' => 'Clasificación SAPU', 'value' => $center->tipo_sapu],
        ],
    ])
</div>
