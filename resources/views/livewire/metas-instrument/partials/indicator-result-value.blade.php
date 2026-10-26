{{-- Presentación fija a 2 decimales en tablas Metas (el cálculo sigue usando decimales del indicador). --}}
{{ number_format((float) $value, 2) }}@if (filled($unidadMedida ?? null))
    @if (($unidadMedida ?? '') === '%')
        %
    @else
        <small class="text-muted">{{ $unidadMedida }}</small>
    @endif
@endif
