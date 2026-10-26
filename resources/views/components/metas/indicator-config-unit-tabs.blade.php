@props([
    'unitStatuses' => collect(),
    'activeUnitId' => null,
])

@php
    $completeCount = $unitStatuses->where('is_complete', true)->count();
    $totalCount = $unitStatuses->count();
    $activeUnit = $activeUnitId ? $unitStatuses->firstWhere('id', $activeUnitId) : null;
@endphp

<div class="metas-config-unit-context" style="margin-bottom: 1rem;">
    @if ($activeUnit || $totalCount > 0)
        <p class="text-muted" style="margin-bottom: 0.5rem;">
            @if ($activeUnit)
                <strong>Unidad activa:</strong> <code>{{ $activeUnit['codigo'] }}</code>
                @if ($activeUnit['nombre'] ?? null)
                    — {{ $activeUnit['nombre'] }}
                @endif
            @endif
            @if ($totalCount > 0)
                @if ($activeUnit)
                    &nbsp;|&nbsp;
                @endif
                <span class="label label-{{ $completeCount === $totalCount ? 'success' : 'warning' }}">
                    {{ $completeCount }} de {{ $totalCount }} unidades completas
                </span>
            @endif
        </p>
    @endif
</div>

@if ($unitStatuses->isEmpty())
    <div class="alert alert-warning">
        No hay unidades registradas para este establecimiento. Cree unidades
        desde la columna <strong>Unidades</strong> más arriba.
    </div>
@else
    <ul class="nav nav-tabs metas-config-unit-tabs" role="tablist">
        @foreach ($unitStatuses as $status)
            <li role="presentation" class="{{ (int) $activeUnitId === (int) $status['id'] ? 'active' : '' }}"
                wire:key="config-unit-tab-{{ $status['id'] }}">
                <a href="#" role="tab" wire:click.prevent="selectConfigMeasurementUnit({{ $status['id'] }})"
                    wire:loading.attr="disabled" wire:target="selectConfigMeasurementUnit({{ $status['id'] }})"
                    title="{{ $status['message'] ?? 'Configuración completa' }}">
                    <code>{{ $status['codigo'] }}</code>
                    @if ($status['is_complete'])
                        <span class="label label-success" style="margin-left: 4px;">OK</span>
                    @else
                        <span class="label label-warning" style="margin-left: 4px;">!</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
@endif
