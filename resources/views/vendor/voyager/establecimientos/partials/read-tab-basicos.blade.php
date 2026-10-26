@php
    $center = $dataTypeContent;
    $origenVal = $center->origen?->value ?? null;
    $origenBadge = match ($origenVal) {
        'api' => 'info',
        'manual' => 'warning',
        default => 'neutral',
    };
    $origenLabel = match ($origenVal) {
        'api' => 'Catálogo nacional (datos.gob.cl)',
        'manual' => 'Alta manual',
        default => null,
    };
@endphp
<div class="cen-read-detail">
    <div class="cen-read-hero">
        <div class="cen-read-hero__icon">
            <i class="voyager-shop"></i>
        </div>
        <div class="cen-read-hero__body">
            <h2 class="cen-read-hero__title">{{ $center->nombre_oficial }}</h2>
            <p class="cen-read-hero__subtitle">{{ $center->nombre_corto }}</p>
            <div class="cen-read-hero__badges">
                <span class="cen-read-badge cen-read-badge--info">{{ $center->codigo }}</span>
                @if ($center->cod_antiguo)
                    <span class="cen-read-badge cen-read-badge--neutral">Antiguo: {{ $center->cod_antiguo }}</span>
                @endif
                @if ($origenLabel)
                    <span
                        class="cen-read-badge cen-read-badge--{{ $origenBadge === 'info' ? 'info' : ($origenBadge === 'warning' ? 'warning' : 'neutral') }}">{{ $origenLabel }}</span>
                @endif
            </div>
        </div>
        <dl class="cen-read-hero__meta">
            <dt>Última sincronización</dt>
            <dd>{{ $center->synced_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            <dt>Actualizado</dt>
            <dd>{{ $center->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd>
        </dl>
    </div>

    @include('voyager::establecimientos.partials.read-detail-section', [
        'title' => 'Identificación',
        'icon' => 'voyager-key',
        'items' => [
            ['label' => 'Código nacional', 'value' => $center->codigo, 'highlight' => true],
            ['label' => 'Código antiguo', 'value' => $center->cod_antiguo],
            [
                'label' => 'Origen del registro',
                'value' => $origenLabel ?? $center->origen,
                'badge' => $origenBadge,
            ],
        ],
    ])

    @include('voyager::establecimientos.partials.read-detail-section', [
        'title' => 'Clasificación',
        'icon' => 'voyager-categories',
        'twoCol' => true,
        'items' => [
            ['label' => 'Tipo de establecimiento', 'value' => $center->cen_type, 'badge' => 'info'],
            ['label' => 'Ámbito de funcionamiento', 'value' => $center->cen_scope],
        ],
    ])

    @include('voyager::establecimientos.partials.read-detail-section', [
        'title' => 'Registro en Centinela',
        'icon' => 'voyager-calendar',
        'twoCol' => true,
        'items' => [
            ['label' => 'Creado', 'value' => $center->created_at?->format('d/m/Y H:i')],
            [
                'label' => 'Última sincronización con catálogo nacional',
                'value' => $center->synced_at?->format('d/m/Y H:i'),
            ],
        ],
    ])
</div>

@if (isset($authUser) && $authUser->hasRole('admin') && $center->origen?->value === 'manual')
    <div class="panel panel-bordered" style="margin: 0 15px 20px;">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="voyager-link"></i> Vincular con código del catálogo nacional</h3>
        </div>
        <div class="panel-body">
            <p class="help-block text-muted">Vincula este registro manual al código del catálogo datos.gob.cl y dispara
                una sincronización.</p>
            <form method="post" action="{{ route('voyager.establecimientos.merge-codigo', $center->getKey()) }}">
                @csrf
                <div class="form-group">
                    <label for="national_codigo">Código nacional del establecimiento</label>
                    <input type="text" name="national_codigo" id="national_codigo" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-warning btn-sm"
                    onclick="return confirm('¿Vincular este registro manual al código nacional?');">
                    Fusionar y sincronizar
                </button>
            </form>
        </div>
    </div>
@endif
