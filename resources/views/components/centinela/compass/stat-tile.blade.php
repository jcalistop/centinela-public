@props(['value', 'label', 'icon' => null, 'accent' => false])

<div {{ $attributes->class(['cen-stat-tile', 'cen-stat-tile--accent' => $accent]) }}>
    @if ($icon)
        <i class="{{ $icon }} cen-stat-tile__icon" aria-hidden="true"></i>
    @endif
    <div class="cen-stat-tile__value">{{ $value }}</div>
    <p class="cen-stat-tile__label">{{ $label }}</p>
</div>
