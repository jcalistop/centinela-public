@php
    use App\Support\Voyager\VoyagerBackedEnumBreadForm;

    $display = VoyagerBackedEnumBreadForm::displayLabel($value ?? null);
    $isEmpty = $display === '—';
    $badgeClass = match ($badge ?? null) {
        'info' => 'cen-read-badge--info',
        'success' => 'cen-read-badge--success',
        'warning' => 'cen-read-badge--warning',
        'danger' => 'cen-read-badge--danger',
        'neutral' => 'cen-read-badge--neutral',
        default => null,
    };
@endphp
<div @class(['cen-read-item', 'cen-read-item--wide' => !empty($wide)])>
    <span class="cen-read-item__label">{{ $label }}</span>
    @if ($badgeClass && !$isEmpty)
        <p class="cen-read-item__value">
            <span class="cen-read-badge {{ $badgeClass }}">{{ $display }}</span>
        </p>
    @elseif (!empty($highlight) && !$isEmpty)
        <p class="cen-read-item__value cen-read-item__value--highlight">{{ $display }}</p>
    @elseif ($isEmpty)
        <p class="cen-read-item__value cen-read-item__value--muted">{{ $display }}</p>
    @else
        <p class="cen-read-item__value">{{ $display }}</p>
    @endif
</div>
