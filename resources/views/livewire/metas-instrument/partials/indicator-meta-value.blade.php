{{-- Meta en tablas: operador + valor a 2 decimales + unidad (% u otra). --}}
@php
    $metaValue = $value ?? null;
    $metaOp = $metaOperador ?? ($metaOp ?? null);
    $unidadMedida = $unidadMedida ?? null;
@endphp
@if ($metaValue === null || $metaValue === '')
    <span class="text-muted">—</span>
@else
    @if (filled($metaOp))
        <small class="text-muted">{{ $metaOp }}</small>
    @endif
    {{ number_format((float) $metaValue, 2) }}@if (filled($unidadMedida))
        @if ($unidadMedida === '%')
            %
        @else
            <small class="text-muted">{{ $unidadMedida }}</small>
        @endif
    @endif
@endif
