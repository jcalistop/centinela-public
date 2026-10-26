@props(['indicatorType'])

@php
    $label = match ($indicatorType->route) {
        'metas-18834' => 'Panel metas Ley 18.834',
        'metas-19664' => 'Panel metas Ley 19.664',
        'metas-20707' => 'Panel metas Ley 20.707',
        default => 'Panel ' . $indicatorType->nombre_indicador,
    };
@endphp

<a href="{{ route('metas.instrument.index', $indicatorType) }}"
    {{ $attributes->merge(['class' => 'btn btn-success btn-sm']) }}>
    <i class="voyager-home"></i> {{ $label }}
</a>
