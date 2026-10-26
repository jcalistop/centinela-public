@php
    $label = $label ?? 'Ver PDF';
    $title = $title ?? ($filename ?? $label);
@endphp
<a href="#" class="centinela-pdf-preview-trigger" role="button" data-pdf-url="{{ $url }}"
    data-pdf-title="{{ $title }}">{{ $label }}</a>
