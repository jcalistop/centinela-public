@php
    $center = $dataTypeContent;
    $hieNombre = $center->cenHieDependency?->nombre;
    $hieCodigo = $center->cenHieDependency?->codigo;
@endphp
<div class="cen-read-detail">
    @if ($hieNombre)
        <div class="cen-read-spotlight">
            <p class="cen-read-spotlight__label">Servicio de salud / dependencia jerárquica</p>
            <p class="cen-read-spotlight__value">{{ $hieNombre }}</p>
            @if ($hieCodigo)
                <p class="cen-read-spotlight__hint">Código SS: <strong>{{ $hieCodigo }}</strong></p>
            @endif
        </div>
    @endif

    @include('voyager::establecimientos.partials.read-detail-section', [
        'title' => 'Administración y SNSS',
        'icon' => 'voyager-company',
        'twoCol' => true,
        'items' => [
            [
                'label' => 'Dependencia administrativa',
                'value' => $center->cen_adm_dependency,
                'badge' => 'neutral',
            ],
            [
                'label' => 'Pertenencia SNSS',
                'value' => $center->pertenencia_snss,
                'badge' => ($center->pertenencia_snss?->value ?? '') === 'Perteneciente' ? 'success' : 'neutral',
            ],
            ['label' => 'Certificación', 'value' => $center->cen_certification],
        ],
    ])

    @include('voyager::establecimientos.partials.read-detail-section', [
        'title' => 'Relación con establecimiento madre',
        'icon' => 'voyager-git',
        'twoCol' => true,
        'items' => [
            ['label' => 'Código madre (nuevo)', 'value' => $center->codigo_madre],
            ['label' => 'Código madre antiguo', 'value' => $center->cod_madre_antiguo],
        ],
    ])
</div>
