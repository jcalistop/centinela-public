@props([
    'message' => null,
    'variant' => 'row',
])

@php
    $tooltip = 'Este establecimiento incluido en la medición requiere completar la configuración de indicadores.';
    if ($message) {
        $tooltip = $message;
    }
@endphp

<div {{ $attributes->class(['componente-corte-alerta', 'metas-establishment-config-alert']) }}
    title="{{ $tooltip }}">
    <span class="glyphicon glyphicon-exclamation-sign text-warning" aria-hidden="true"></span>
    <strong>Configuración incompleta</strong>
    <div class="small text-muted">
        @if ($variant === 'banner' && $message)
            {{ $message }}
        @else
            {{ $message ?? 'Revise ponderaciones y metas efectivas en Indicadores' }}
        @endif
    </div>
</div>
