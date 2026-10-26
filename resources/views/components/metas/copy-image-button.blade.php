@props(['target', 'label' => 'Copiar imagen'])

<button type="button" {{ $attributes->class(['btn', 'btn-warning', 'btn-sm', 'no-print', 'metas-copy-image-button']) }}
    data-metas-copy-image="{{ $target }}">
    <i class="voyager-photo" aria-hidden="true"></i> {{ $label }}
</button>
