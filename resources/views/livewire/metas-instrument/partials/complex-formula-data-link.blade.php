@php
    $dataEntryUrl = $dataEntryUrl ?? null;
@endphp

<td colspan="2" class="text-center">
    <span class="text-muted">Fórmula compleja:</span>
    @if ($dataEntryUrl)
        <a href="{{ $dataEntryUrl }}">ver detalle de datos</a>
    @else
        <span class="text-muted">ver detalle de datos</span>
    @endif
</td>
