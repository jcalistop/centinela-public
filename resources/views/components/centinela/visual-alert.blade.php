@props(['alert', 'display' => 'banner', 'variant' => 'inline'])

@php
    /** @var \App\Support\Alerts\VisualAlert $alert */
    $severityClass = match ($alert->severity->value) {
        'success' => 'centinela-visual-alert--success',
        'info' => 'centinela-visual-alert--info',
        default => 'centinela-visual-alert--warning',
    };
    $iconClass = match ($alert->severity->value) {
        'success' => 'glyphicon-ok-sign text-success',
        'info' => 'glyphicon-info-sign text-info',
        default => 'glyphicon-exclamation-sign text-warning',
    };
    $tooltip = e($alert->tooltipText());
    $descId = 'centinela-alert-desc-' . md5($alert->id . $alert->anchorKey . $alert->placement->value);
    $variantClass = $variant === 'corner' ? 'centinela-visual-alert-icon--corner' : '';
@endphp

@if ($display === 'icon')
    <span id="{{ $descId }}" class="sr-only">{{ $tooltip }}</span>
    <span class="centinela-visual-alert-icon {{ $severityClass }} {{ $variantClass }}" data-toggle="tooltip"
        data-placement="top" data-container="body" data-centinela-visual-alert-tooltip title="{{ $tooltip }}"
        aria-describedby="{{ $descId }}" aria-label="{{ e($alert->title) }}" tabindex="0" role="img">
        <span class="glyphicon {{ $iconClass }}" aria-hidden="true"></span>
    </span>
@else
    <span id="{{ $descId }}" class="sr-only">{{ $tooltip }}</span>
    <div class="centinela-visual-alert {{ $severityClass }}" data-toggle="tooltip" data-placement="top"
        data-container="body" data-centinela-visual-alert-tooltip title="{{ $tooltip }}"
        aria-describedby="{{ $descId }}">
        <span class="glyphicon {{ $iconClass }}" aria-hidden="true"></span>
        <strong>{{ $alert->title }}</strong>
        @if ($alert->detail)
            <div class="small text-muted">{{ $alert->detail }}</div>
        @endif
    </div>
@endif
