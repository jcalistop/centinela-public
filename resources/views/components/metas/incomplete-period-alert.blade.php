@props([
    'monthLabel' => null,
    'centerCount' => null,
    'periodCount' => null,
    'message' => null,
    'periodLabels' => null,
    'variant' => 'catalog',
])

@php
    $tooltip = 'Existen períodos con captura parcial para este indicador en su Servicio de Salud.';
    if ($message) {
        $tooltip = $message;
    }
    if (is_array($periodLabels) && $periodLabels !== []) {
        $tooltip .= ' — ' . implode(', ', $periodLabels);
    }
@endphp

<div {{ $attributes->class([
    'componente-corte-alerta',
    'metas-incomplete-period-alert',
    'metas-alert--compact' => $variant === 'compact',
]) }}
    title="{{ $tooltip }}">
    <span class="glyphicon glyphicon-exclamation-sign text-warning" aria-hidden="true"></span>
    <strong>Carga incompleta</strong>
    <div class="small text-muted">
        @if ($variant === 'center' && $periodCount !== null)
            {{ $periodCount === 1 ? '1 período' : $periodCount . ' períodos' }} · Complete o vacíe el mes
        @elseif (in_array($variant, ['banner', 'compact'], true) && $centerCount !== null && $centerCount > 0)
            {{ $centerCount === 1 ? '1 establecimiento afectado' : $centerCount . ' establecimientos afectados' }}
            @if ($message)
                · {{ $message }}
            @endif
        @else
            @if ($monthLabel)
                Actualizado a {{ $monthLabel }} ·
            @endif
            Revise meses con captura parcial en carga o desempeño
        @endif
    </div>
</div>
