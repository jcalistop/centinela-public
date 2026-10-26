@props(['title', 'icon' => null, 'description' => null])

<div {{ $attributes->class(['cen-dashboard-block']) }}>
    <div class="cen-dashboard-block__head">
        <div class="cen-dashboard-block__head-main">
            <h3 class="cen-dashboard-block__title">
                @if ($icon)
                    <i class="{{ $icon }}" aria-hidden="true"></i>
                @endif
                {{ $title }}
            </h3>
            @if ($description)
                <p class="cen-dashboard-block__description">{{ $description }}</p>
            @endif
        </div>
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
