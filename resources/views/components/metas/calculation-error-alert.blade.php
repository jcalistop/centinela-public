@props([
    'monthLabel' => null,
    'centerCount' => null,
    'periodCount' => null,
    'message' => null,
    'variant' => 'catalog',
])

@php
    $tooltip = 'Existen resultados con error de cálculo para este indicador en su Servicio de Salud.';
    if ($message) {
        $tooltip = $message;
    }
@endphp

<div {{ $attributes->class([
    'componente-corte-alerta',
    'metas-calculation-error-alert',
    'metas-alert--compact' => $variant === 'compact',
]) }}
    title="{{ $tooltip }}">
    <span class="glyphicon glyphicon-exclamation-sign text-warning" aria-hidden="true"></span>
    <strong>Error en resultados</strong>
    <div class="small text-muted">
        @if ($variant === 'center' && $periodCount !== null)
            {{ $periodCount === 1 ? '1 periodo' : $periodCount . ' periodos' }} · Revise datos cargados
        @elseif (in_array($variant, ['banner', 'compact'], true) && $centerCount !== null && $centerCount > 0)
            {{ $centerCount === 1 ? '1 establecimiento afectado' : $centerCount . ' establecimientos afectados' }}
            @if ($message)
                · {{ $message }}
            @endif
        @else
            @if ($monthLabel)
                Actualizado a {{ $monthLabel }} ·
            @endif
            Revise datos cargados o desempeño por establecimiento
        @endif
    </div>
</div>
