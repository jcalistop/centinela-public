@props([
    'message' => null,
    'variant' => 'catalog',
])

@php
    $tooltip = 'Este indicador no puede calcular resultados hasta corregir su fórmula.';
    if ($message) {
        $tooltip = $message;
    }
@endphp

<div {{ $attributes->class([
    'componente-corte-alerta',
    'metas-formula-issue-alert',
    'metas-alert--compact' => $variant === 'compact',
]) }}
    title="{{ $tooltip }}">
    <span class="glyphicon glyphicon-exclamation-sign text-warning" aria-hidden="true"></span>
    <strong>Fórmula incompleta</strong>
    <div class="small text-muted">
        @if (in_array($variant, ['banner', 'compact'], true) && $message)
            {{ $message }}
        @else
            Revise la fórmula en el catálogo del indicador
        @endif
    </div>
</div>
