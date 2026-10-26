@props(['href', 'label'])

<a {{ $attributes->class(['btn', 'btn-success', 'btn-sm']) }} href="{{ $href }}">
    <i class="voyager-angle-left" aria-hidden="true"></i> {{ $label }}
</a>
