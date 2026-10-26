@props([
    'label' => 'Imprimir',
])

<button type="button" {{ $attributes->class(['btn', 'btn-warning', 'btn-sm', 'no-print', 'metas-print-button']) }}
    onclick="window.print()">
    <i class="voyager-book" aria-hidden="true"></i> {{ $label }}
</button>
