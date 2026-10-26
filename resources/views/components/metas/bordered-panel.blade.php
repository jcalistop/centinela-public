@props(['title' => null, 'variant' => 'bordered', 'icon' => null])

@php
    $panelSkin = match ($variant) {
        'danger' => 'panel-danger',
        'warning' => 'panel-warning',
        'default' => 'panel-default',
        default => 'panel-bordered',
    };
@endphp

<div {{ $attributes->class(['panel', $panelSkin]) }}>
    @if ($title || isset($heading) || isset($headingActions))
        <div @class(['panel-heading', 'clearfix' => isset($headingActions)])>
            <h3 @class(['panel-title', 'pull-left' => isset($headingActions)])>
                @if ($icon)
                    <i class="{{ $icon }}"></i>
                @endif
                @isset($heading)
                    {{ $heading }}
                @else
                    {{ $title }}
                @endisset
            </h3>
            @isset($headingActions)
                <div class="pull-right metas-panel-heading-actions">
                    {{ $headingActions }}
                </div>
            @endisset
        </div>
    @endif
    <div class="panel-body">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="panel-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
