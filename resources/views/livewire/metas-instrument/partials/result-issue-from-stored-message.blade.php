@php
    $missingData = \App\Models\MetResult::storedErrorMessageIndicatesMissingData($storedMessage ?? null);
    $noCasesInPeriod = \App\Models\MetResult::storedErrorMessageIndicatesNoCasesInPeriod($storedMessage ?? null);
    $asSeparateCells = $asSeparateCells ?? false;
    $primaryCellClass = $primaryCellClass ?? '';
    $separateInputCells = (int) ($separateInputCells ?? 2);
@endphp
@if ($asSeparateCells)
    {{-- Celdas sueltas: evita romper la tabla cuando hay columnas no-capture (p. ej. PNG Autoevaluación). --}}
    @for ($i = 0; $i < max(1, $separateInputCells); $i++)
        <td @class([
            'text-center',
            $primaryCellClass => filled($primaryCellClass),
        ])>—</td>
    @endfor
    <td class="text-center">
        @if ($missingData)
            {{-- "Datos al" ya informa ausencia; no repetir "Sin datos" ni usar colspan. --}}
            <span class="text-muted" title="{{ $storedMessage }}">—</span>
        @elseif ($noCasesInPeriod)
            <span class="text-muted" title="{{ $storedMessage }}">Sin casos en el periodo</span>
        @else
            <span class="label label-danger" title="{{ $storedMessage }}">Error</span>
        @endif
    </td>
    <td class="text-center">
        <span class="text-muted">—</span>
    </td>
@else
    <td colspan="{{ $colspan }}" class="text-center">
        @if ($missingData)
            <span class="text-muted" title="{{ $storedMessage }}">Sin datos</span>
        @elseif ($noCasesInPeriod)
            <span class="text-muted" title="{{ $storedMessage }}">Sin casos en el periodo</span>
        @else
            <span class="label label-danger" title="{{ $storedMessage }}">Error</span>
        @endif
    </td>
@endif
