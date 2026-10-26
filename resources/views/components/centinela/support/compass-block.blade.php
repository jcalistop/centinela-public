@props(['title', 'icon' => null])

<div {{ $attributes->class(['cen-dashboard-block']) }}>
    <div class="cen-dashboard-block__head">
        <h2 class="cen-dashboard-block__title">
            @if ($icon)
                <i class="{{ $icon }}" aria-hidden="true"></i>
            @endif
            {{ $title }}
        </h2>
        @isset($actions)
            <div class="cen-dashboard-block__actions">
                {{ $actions }}
            </div>
        @endisset
    </div>
    <div class="cen-dashboard-block__body">
        {{ $slot }}
    </div>
</div>
