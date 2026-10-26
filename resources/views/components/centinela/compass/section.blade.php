@props(['title', 'icon' => null, 'flush' => false])

<div {{ $attributes->class(['cen-read-section']) }}>
    <div class="cen-read-section__head">
        @if ($icon)
            <i class="{{ $icon }}" aria-hidden="true"></i>
        @endif
        <h4 class="cen-read-section__title">{{ $title }}</h4>
    </div>
    <div @class([
        'cen-read-section__body',
        'cen-read-section__body--flush' => $flush,
    ])>
        {{ $slot }}
    </div>
</div>
