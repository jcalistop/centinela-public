@props([
    'ultimoMesCargado' => null,
    'estadoCarga',
])

@php
    $status =
        $estadoCarga instanceof \App\Enums\Metas\MetDataLoadStatus
            ? $estadoCarga
            : \App\Enums\Metas\MetDataLoadStatus::from((string) $estadoCarga);
    $mesLabel = app(\App\Services\Metas\MetIndicatorLastLoadedMonthService::class)->etiquetaMes(
        $ultimoMesCargado !== null ? (int) $ultimoMesCargado : null,
    );
@endphp

<td class="text-center metas-datos-al-cell" title="Último mes con dato ingresado">
    <span class="label {{ $status->labelClass() }} metas-datos-al-badge">
        {{ $mesLabel }}
        @if ($status->showsMonthSuffix() && $ultimoMesCargado !== null)
            <small class="metas-datos-al-badge__suffix">· {{ $status->monthSuffix() }}</small>
        @endif
    </span>
</td>
