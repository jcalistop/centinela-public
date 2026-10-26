@props(['href', 'mono' => false])

<a href="{{ $href }}"
    {{ $attributes->class(['cen-link', 'cen-link--external' => !$mono, 'cen-link--mono' => $mono])->merge([
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
    ]) }}>
    {{ $slot }}
    @if (!$mono)
        <i class="voyager-external" aria-hidden="true"></i>
    @endif
</a>
