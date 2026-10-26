@props([
    'status' => null,
    'variant' => 'banner',
])

@php
    $isPartial = $status === \App\Services\Metas\MetFieldSsConfigService::STATUS_PARTIAL;
    $detail = $isPartial
        ? 'Tipos de entrada pendientes para su Servicio de Salud'
        : 'Sin configuración de tipos de entrada/agregación por SS para este indicador';
@endphp

<div {{ $attributes->class([
    'componente-corte-alerta',
    'metas-field-ss-config-pending-alert',
    'metas-alert--compact' => $variant === 'compact',
]) }}
    title="Falta definir tipos de entrada y agregación para este indicador en su Servicio de Salud.">
    <span class="glyphicon glyphicon-exclamation-sign text-warning" aria-hidden="true"></span>
    <strong>Carga de datos</strong>
    <div class="small text-muted">
        {{ $detail }}. Contacte a Control de Gestión antes de cargar datos en este indicador.
    </div>
</div>
